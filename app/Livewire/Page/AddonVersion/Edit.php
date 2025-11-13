<?php

declare(strict_types=1);

namespace App\Livewire\Page\AddonVersion;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VirusTotalLink;
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

class Edit extends Component
{
    use RendersMarkdownPreview;
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
    public array $virusTotalLinks = [];

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
    public function mount(Addon $addon, AddonVersion $addonVersion): void
    {
        $this->honeypotData = new HoneypotData;

        $this->addonVersion = $addonVersion->loadMissing('addon.mod');

        $this->authorize('update', $this->addonVersion);

        $this->version = $this->addonVersion->version;
        $this->description = $this->addonVersion->description ?? '';
        $this->link = $this->addonVersion->link;
        $this->modVersionConstraint = $this->addonVersion->mod_version_constraint;

        // Load existing VirusTotal links
        $this->virusTotalLinks = $addonVersion->virusTotalLinks->map(fn (VirusTotalLink $link): array => [
            'url' => $link->url,
            'label' => $link->label ?? '',
        ])->all();

        // Ensure at least one empty link field is present
        if (empty($this->virusTotalLinks)) {
            $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
        }

        $this->publishedAt = $this->addonVersion->published_at ? Date::parse($this->addonVersion->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d\TH:i') : null;

        $this->updatedModVersionConstraint();
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
        if (empty($this->modVersionConstraint) || ! $this->addonVersion->addon->mod_id) {
            $this->matchingModVersions = [];

            return;
        }

        try {
            $modVersions = ModVersion::query()
                ->where('mod_id', $this->addonVersion->addon->mod_id)
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
        $this->authorize('update', $this->addonVersion);

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
                    'label' => ! empty($virusTotalLink['label']) ? $virusTotalLink['label'] : '',
                ]);
            }
        }

        Track::event(TrackingEventType::ADDON_VERSION_EDIT, $this->addonVersion);

        Session::flash('success', 'Addon Version has been Successfully Updated');

        $this->redirect($this->addonVersion->addon->detail_url);
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.addon-version.edit');
    }
}
