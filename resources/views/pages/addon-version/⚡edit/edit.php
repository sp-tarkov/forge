<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\VirusTotalLink;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use App\Support\VersionMatcher;
use Flux\Flux;
use Illuminate\Support\Facades\Date;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;
use Stevebauman\Purify\Facades\Purify;

new #[Layout('layouts::base')] class extends Component
{
    use UsesSpamProtection;

    /**
     * The addon version to edit.
     */
    public AddonVersion $addonVersion;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The version number.
     */
    #[Validate(['required', 'string', 'max:50', new SemverRule()])]
    public string $version = '';

    /**
     * The description of the addon version.
     */
    #[Validate('required|string')]
    public string $description = '';

    /**
     * The link to the addon version.
     */
    public string $link = '';

    /**
     * The mod version constraint.
     */
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule()])]
    public string $modVersionConstraint = '';

    /**
     * The links to the virus total scans of the addon version.
     *
     * @var array<int, array{url: string, label: string}>
     */
    public array $virusTotalLinks = [];

    /**
     * The published at date of the addon version.
     */
    #[Validate('nullable|date')]
    public ?string $publishedAtDate = null;

    /**
     * The published at time of the addon version.
     */
    #[Validate('nullable|date_format:H:i')]
    public ?string $publishedAtTime = null;

    /**
     * The matching mod versions for the current constraint.
     *
     * @var array<int, array{version: string, id: int}>
     */
    public array $matchingModVersions = [];

    /**
     * The mod dependencies for this addon version.
     *
     * @var array<int, array{id: ?int, modId: string, constraint: string}>
     */
    public array $dependencies = [];

    /**
     * The matching versions for each dependency constraint.
     *
     * @var array<int, array<int, array{mod_name: string, version: string}>>
     */
    public array $matchingDependencyVersions = [];

    /**
     * The DirectDownloadLink rule instance (for content length extraction).
     */
    private DirectDownloadLink $downloadLinkRule;

    /**
     * Mount the component.
     */
    public function mount(Addon $addon, AddonVersion $addonVersion): void
    {
        $this->honeypotData = new HoneypotData();

        $this->addonVersion = $addonVersion->loadMissing('addon.mod');

        $this->authorize('update', $this->addonVersion);

        $this->version = $this->addonVersion->version;
        $this->description = $this->addonVersion->description ?? '';
        $this->link = $this->addonVersion->link;
        $this->modVersionConstraint = $this->addonVersion->mod_version_constraint;

        // Load existing VirusTotal links
        $this->virusTotalLinks = $addonVersion->virusTotalLinks
            ->map(
                fn (VirusTotalLink $link): array => [
                    'url' => $link->url,
                    'label' => $link->label ?? '',
                ],
            )
            ->all();

        // Ensure at least one empty link field is present
        if ($this->virusTotalLinks === []) {
            $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
        }

        // Load existing dependencies
        $this->dependencies = $addonVersion->dependencies
            ->map(
                fn (Dependency $dependency): array => [
                    'id' => $dependency->id,
                    'modId' => (string) $dependency->dependent_mod_id,
                    'constraint' => $dependency->constraint,
                ],
            )
            ->all();

        // Update matching versions for each existing dependency
        foreach ($this->dependencies as $index => $dependency) {
            $this->updateMatchingDependencyVersions($index);
        }

        if ($this->addonVersion->published_at) {
            $publishedAtLocal = Date::parse($this->addonVersion->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC');
            $this->publishedAtDate = $publishedAtLocal->format('Y-m-d');
            $this->publishedAtTime = $publishedAtLocal->format('H:i');
        }

        $this->updatedModVersionConstraint();
    }

    /**
     * Preview markdown content.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (in_array(mb_trim($content), ['', '0'], true)) {
            return '<p class="text-slate-500 italic">'.__('Nothing to preview.').'</p>';
        }

        $converter = new GithubFlavoredMarkdownConverter();
        $html = $converter->convert($content)->getContent();

        /** @var string */
        return Purify::config($purifyConfig)->clean($html);
    }

    /**
     * Add a new VirusTotal link field.
     */
    public function addVirusTotalLink(): void
    {
        $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
    }

    /**
     * Remove a VirusTotal link field.
     */
    public function removeVirusTotalLink(int $index): void
    {
        unset($this->virusTotalLinks[$index]);
        $this->virusTotalLinks = array_values($this->virusTotalLinks);
    }

    /**
     * Add a new dependency.
     */
    public function addDependency(): void
    {
        $this->dependencies[] = ['id' => null, 'modId' => '', 'constraint' => ''];
    }

    /**
     * Remove a dependency.
     */
    public function removeDependency(int $index): void
    {
        unset($this->dependencies[$index]);
        unset($this->matchingDependencyVersions[$index]);
        $this->dependencies = array_values($this->dependencies);
        $this->matchingDependencyVersions = array_values($this->matchingDependencyVersions);
    }

    /**
     * Update the mod ID for a dependency via JavaScript event.
     */
    #[Renderless]
    public function updateDependencyModId(int $index, ?int $modId): void
    {
        if (isset($this->dependencies[$index])) {
            $this->dependencies[$index]['modId'] = $modId !== null ? (string) $modId : '';

            // Auto-populate constraint with latest version if the constraint is currently empty.
            if ($modId !== null && $this->dependencies[$index]['constraint'] === '') {
                $versions = ModVersion::versionNumbers($modId);
                if ($versions !== []) {
                    $this->dependencies[$index]['constraint'] = '~'.$versions[0];
                }
            }

            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update the matching dependency versions when the constraint changes.
     */
    public function updatedDependencies(mixed $value, string $path): void
    {
        if (str_ends_with($path, '.constraint')) {
            $index = (int) explode('.', $path)[0];
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update the matching mod versions when the constraint changes.
     */
    public function updatedModVersionConstraint(): void
    {
        if ($this->modVersionConstraint === '' || $this->modVersionConstraint === '0' || ! $this->addonVersion->addon->mod_id) {
            $this->matchingModVersions = [];

            return;
        }

        try {
            $modVersions = ModVersion::query()
                ->where('mod_id', $this->addonVersion->addon->mod_id)
                ->where('disabled', false)
                ->whereNotNull('published_at')
                ->get();

            /** @var array<string> $validVersions */
            $validVersions = $modVersions->pluck('version')->toArray();
            $compatibleVersions = VersionMatcher::satisfiedBy($validVersions, $this->modVersionConstraint);

            $this->matchingModVersions = $modVersions
                ->whereIn('version', $compatibleVersions)
                ->sortByDesc('version')
                ->map(
                    fn (ModVersion $version): array => [
                        'id' => $version->id,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();
        } catch (Exception) {
            $this->matchingModVersions = [];
        }
    }

    /**
     * Save the addon version.
     */
    public function save(): void
    {
        $this->authorize('update', $this->addonVersion);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Create and configure the DirectDownloadLink rule
        $this->downloadLinkRule = new DirectDownloadLink();

        // Build validation rules
        $rules = [
            'link' => ['required', 'string', 'url', 'starts_with:https://,http://', $this->downloadLinkRule],
            'version' => ['required', 'string', 'max:50', new SemverRule()],
            'description' => 'required|string',
            'modVersionConstraint' => ['required', 'string', 'max:75', new SemverConstraintRule()],
            'publishedAtDate' => 'nullable|date',
            'publishedAtTime' => 'nullable|date_format:H:i',
        ];

        // VirusTotal links validation
        $rules['virusTotalLinks'] = 'required|array|min:1';
        $rules['virusTotalLinks.*.url'] = 'required|string|url|starts_with:https://www.virustotal.com/';
        $rules['virusTotalLinks.*.label'] = 'nullable|string|max:255';

        // Dependencies validation (optional)
        $rules['dependencies'] = 'array';
        $rules['dependencies.*.modId'] = 'required_with:dependencies.*.constraint|exists:mods,id';
        $rules['dependencies.*.constraint'] = ['required_with:dependencies.*.modId', new SemverConstraintRule()];

        // Build custom messages
        $messages = [
            'virusTotalLinks.required' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.min' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.*.url.required' => 'Please enter a valid VirusTotal URL.',
            'virusTotalLinks.*.url.url' => 'Please enter a valid URL (e.g., https://www.virustotal.com/...).',
            'virusTotalLinks.*.url.starts_with' => 'The URL must start with https://www.virustotal.com/',
            'virusTotalLinks.*.label.max' => 'The label must not exceed 255 characters.',
            'dependencies.*.modId.required_with' => 'Please select a mod for this dependency.',
            'dependencies.*.modId.exists' => 'The selected mod does not exist.',
            'dependencies.*.constraint.required_with' => 'Please enter a version constraint for this dependency.',
        ];

        // Validate all fields
        $this->validate($rules, $messages);

        // Combine date and time into a single published_at value, converting from user timezone to UTC.
        $publishedAtCarbon = null;
        if ($this->publishedAtDate !== null && $this->publishedAtDate !== '') {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $dateTimeString = $this->publishedAtDate.' '.($this->publishedAtTime ?? '00:00');
            $publishedAtCarbon = Date::parse($dateTimeString, $userTimezone)->setTimezone('UTC')->second(0);
        }

        $this->addonVersion->update([
            'version' => $this->version,
            'description' => $this->description,
            'link' => $this->link,
            'content_length' => $this->downloadLinkRule->contentLength,
            'mod_version_constraint' => $this->modVersionConstraint,
            'published_at' => $publishedAtCarbon,
        ]);

        // Update VirusTotal links - delete existing and recreate
        $this->addonVersion->virusTotalLinks()->delete();
        foreach ($this->virusTotalLinks as $virusTotalLink) {
            if (! empty($virusTotalLink['url'])) {
                $this->addonVersion->virusTotalLinks()->create([
                    'url' => $virusTotalLink['url'],
                    'label' => empty($virusTotalLink['label']) ? '' : $virusTotalLink['label'],
                ]);
            }
        }

        // Update dependencies - delete existing and recreate
        $this->addonVersion->dependencies()->delete();
        foreach ($this->dependencies as $dependency) {
            if (! empty($dependency['modId']) && ($dependency['constraint'] !== '' && $dependency['constraint'] !== '0')) {
                $this->addonVersion->dependencies()->create([
                    'dependent_mod_id' => (int) $dependency['modId'],
                    'constraint' => $dependency['constraint'],
                ]);
            }
        }

        Track::event(TrackingEventType::ADDON_VERSION_EDIT, $this->addonVersion);

        Flux::toast(heading: 'Version Updated', text: 'Addon version has been successfully updated.', variant: 'success');

        $this->redirect($this->addonVersion->addon->detail_url, navigate: true);
    }

    /**
     * Update matching dependency versions for a specific dependency.
     */
    private function updateMatchingDependencyVersions(int $index): void
    {
        if (! isset($this->dependencies[$index])) {
            return;
        }

        $dependency = $this->dependencies[$index];
        $modId = $dependency['modId'];
        $constraint = $dependency['constraint'];

        if (empty($modId) || empty($constraint)) {
            $this->matchingDependencyVersions[$index] = [];

            return;
        }

        try {
            $mod = Mod::query()->find($modId);
            if (! $mod) {
                $this->matchingDependencyVersions[$index] = [];

                return;
            }

            $modVersions = ModVersion::query()->where('mod_id', $modId)->where('disabled', false)->whereNotNull('published_at')->get();

            /** @var array<string> $validVersions */
            $validVersions = $modVersions->pluck('version')->toArray();
            $compatibleVersions = VersionMatcher::satisfiedBy($validVersions, $constraint);

            $this->matchingDependencyVersions[$index] = $modVersions
                ->whereIn('version', $compatibleVersions)
                ->sortByDesc('version')
                ->map(
                    fn (ModVersion $version): array => [
                        'mod_name' => $mod->name,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();
        } catch (Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }
};
