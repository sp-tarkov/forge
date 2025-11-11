<?php

declare(strict_types=1);

namespace App\Livewire\Page\AddonVersion;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class Create extends Component
{
    use UsesSpamProtection;

    /**
     * The addon to create the version for.
     */
    public Addon $addon;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The version number.
     */
    #[Validate(['required', 'string', 'max:50', new SemverRule])]
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
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule])]
    public string $modVersionConstraint = '';

    /**
     * The links to the virus total scans of the addon version.
     *
     * @var array<int, array{url: string, label: string}>
     */
    public array $virusTotalLinks = [
        ['url' => '', 'label' => ''],
    ];

    /**
     * The published at date of the addon version.
     */
    #[Validate('nullable|date')]
    public ?string $publishedAt = null;

    /**
     * The matching mod versions for the current constraint.
     *
     * @var array<int, array{version: string, id: int}>
     */
    public array $matchingModVersions = [];

    /**
     * The DirectDownloadLink rule instance (for content length extraction).
     */
    private DirectDownloadLink $downloadLinkRule;

    /**
     * Mount the component.
     */
    public function mount(Addon $addon): void
    {
        $this->honeypotData = new HoneypotData;

        $this->addon = $addon->loadMissing('mod');

        $this->authorize('create', [AddonVersion::class, $this->addon]);
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
     * Update the matching mod versions when the constraint changes.
     */
    public function updatedModVersionConstraint(): void
    {
        if (empty($this->modVersionConstraint) || ! $this->addon->mod_id) {
            $this->matchingModVersions = [];

            return;
        }

        try {
            $modVersions = ModVersion::query()
                ->where('mod_id', $this->addon->mod_id)
                ->where('disabled', false)
                ->whereNotNull('published_at')
                ->get();

            $validVersions = $modVersions->pluck('version')->toArray();
            $compatibleVersions = Semver::satisfiedBy($validVersions, $this->modVersionConstraint);

            $this->matchingModVersions = $modVersions
                ->whereIn('version', $compatibleVersions)
                ->sortByDesc('version')
                ->map(fn (ModVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                ])
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
        $this->authorize('create', [AddonVersion::class, $this->addon]);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Create and configure the DirectDownloadLink rule
        $this->downloadLinkRule = new DirectDownloadLink;

        // Build validation rules
        $rules = [
            'link' => ['required', 'string', 'url', 'starts_with:https://,http://', $this->downloadLinkRule],
            'version' => ['required', 'string', 'max:50', new SemverRule],
            'description' => 'required|string',
            'modVersionConstraint' => ['required', 'string', 'max:75', new SemverConstraintRule],
            'publishedAt' => 'nullable|date',
        ];

        // VirusTotal links validation
        $rules['virusTotalLinks'] = 'required|array|min:1';
        $rules['virusTotalLinks.*.url'] = 'required|string|url|starts_with:https://www.virustotal.com/';
        $rules['virusTotalLinks.*.label'] = 'nullable|string|max:255';

        // Build custom messages
        $messages = [
            'virusTotalLinks.required' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.min' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.*.url.required' => 'Please enter a valid VirusTotal URL.',
            'virusTotalLinks.*.url.url' => 'Please enter a valid URL (e.g., https://www.virustotal.com/...).',
            'virusTotalLinks.*.url.starts_with' => 'The URL must start with https://www.virustotal.com/',
            'virusTotalLinks.*.label.max' => 'The label must not exceed 255 characters.',
        ];

        // Validate all fields
        $this->validate($rules, $messages);

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        // Zero out seconds for consistency with datetime-local input format.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Date::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        // Create a new addon version instance
        $addonVersion = new AddonVersion([
            'addon_id' => $this->addon->id,
            'version' => $this->version,
            'description' => $this->description,
            'link' => $this->link,
            'content_length' => $this->downloadLinkRule->contentLength,
            'mod_version_constraint' => $this->modVersionConstraint,
            'published_at' => $publishedAtCarbon,
        ]);

        // Save the addon version
        $addonVersion->save();

        // Create VirusTotal links
        foreach ($this->virusTotalLinks as $virusTotalLink) {
            if (! empty($virusTotalLink['url'])) {
                $addonVersion->virusTotalLinks()->create([
                    'url' => $virusTotalLink['url'],
                    'label' => ! empty($virusTotalLink['label']) ? $virusTotalLink['label'] : '',
                ]);
            }
        }

        Track::event(TrackingEventType::ADDON_VERSION_CREATE, $addonVersion);

        Session::flash('success', 'Addon Version has been Successfully Created');

        $this->redirect($this->addon->detail_url);
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.addon-version.create');
    }
}
