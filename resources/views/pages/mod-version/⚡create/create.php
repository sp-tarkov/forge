<?php

declare(strict_types=1);

use App\Enums\FikaCompatibility;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\Scopes\PublishedScope;
use App\Models\Scopes\PublishedSptVersionScope;
use App\Models\SptVersion;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use App\Support\Version;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * @property-read bool $modGuidRequired
 * @property string $newModGuid
 */
new #[Layout('layouts::base')] class extends Component
{
    use RendersMarkdownPreview;
    use UsesSpamProtection;

    /**
     * The mod to create the version for.
     */
    public Mod $mod;

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
     * The description of the mod version.
     */
    #[Validate('required|string')]
    public string $description = '';

    /**
     * The link to the mod version.
     */
    public string $link = '';

    /**
     * The SPT version constraint.
     */
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule()])]
    public string $sptVersionConstraint = '';

    /**
     * The virus total links for the mod version.
     *
     * @var array<int, array{url: string, label: string}>
     */
    public array $virusTotalLinks = [];

    /**
     * The published at date of the mod version.
     */
    #[Validate('nullable|date')]
    public ?string $publishedAtDate = null;

    /**
     * The published at time of the mod version.
     */
    #[Validate('nullable|date_format:H:i')]
    public ?string $publishedAtTime = null;

    /**
     * The matching SPT versions for the current constraint.
     *
     * @var array<int, array{version: string, color_class: string, is_published: bool, publish_date: ?string}>
     */
    public array $matchingSptVersions = [];

    /**
     * Whether to pin the mod version to unpublished SPT versions.
     */
    public bool $pinToSptVersions = false;

    /**
     * The Fika compatibility status for this mod version.
     */
    public string $fikaCompatibilityStatus = 'unknown';

    /**
     * The mod dependencies to be created.
     *
     * @var array<int, array{modId: string, constraint: string}>
     */
    public array $dependencies = [];

    /**
     * The matching mod versions for each dependency constraint.
     *
     * @var array<int, array<int, array{id: int, mod_name: string, version: string}>>
     */
    public array $matchingDependencyVersions = [];

    /**
     * The mod GUID (for dynamic validation and updating).
     */
    public string $modGuid = '';

    /**
     * The new GUID to set on the mod if needed.
     */
    public string $newModGuid = '';

    /**
     * Whether the GUID has been successfully saved.
     */
    public bool $guidSaved = false;

    /**
     * The mod category ID (for checking if category is set).
     */
    public ?int $modCategoryId = null;

    /**
     * The new category ID to set on the mod if needed.
     */
    public string $newModCategoryId = '';

    /**
     * Whether the category has been successfully saved.
     */
    public bool $categorySaved = false;

    /**
     * The DirectDownloadLink rule instance (for content length extraction).
     */
    private ?DirectDownloadLink $downloadLinkRule = null;

    /**
     * Get whether mod GUID is required based on SPT version constraint.
     */
    public function getModGuidRequiredProperty(): bool
    {
        if ($this->sptVersionConstraint === '' || $this->sptVersionConstraint === '0') {
            return false;
        }

        try {
            $validSptVersions = SptVersion::allValidVersions(includeUnpublished: true);
            $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $this->sptVersionConstraint);

            // Check if any compatible version is >= 4.0.0
            foreach ($compatibleSptVersions as $version) {
                if (Semver::satisfies($version, '>=4.0.0')) {
                    return true;
                }
            }
        } catch (Exception) {
            // If there's an error, don't require the GUID
            return false;
        }

        return false;
    }

    /**
     * Update the matching SPT versions when the constraint changes.
     */
    public function updatedSptVersionConstraint(): void
    {
        // Reset GUID saved state when constraint changes
        $this->guidSaved = false;

        if ($this->sptVersionConstraint === '' || $this->sptVersionConstraint === '0') {
            $this->matchingSptVersions = [];

            return;
        }

        try {
            $validSptVersions = SptVersion::allValidVersions(includeUnpublished: true);
            $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $this->sptVersionConstraint);

            // Get the matching versions
            $this->matchingSptVersions = SptVersion::query()
                ->withoutGlobalScope(PublishedSptVersionScope::class)
                ->whereIn('version', $compatibleSptVersions)
                ->select(['version', 'color_class', 'publish_date'])
                ->orderByDesc('version_major')
                ->orderByDesc('version_minor')
                ->orderByDesc('version_patch')
                ->orderBy('version_labels')
                ->get()
                ->map(
                    fn (SptVersion $version): array => [
                        'version' => $version->version,
                        'color_class' => $version->color_class,
                        'is_published' => ! is_null($version->publish_date) && $version->publish_date->lte(now()),
                        'publish_date' => $version->publish_date?->format('Y-m-d H:i:s'),
                    ],
                )
                ->all();
        } catch (Exception) {
            $this->matchingSptVersions = [];
        }
    }

    /**
     * Check if there are any unpublished SPT versions in the matching list.
     */
    public function hasUnpublishedSptVersions(): bool
    {
        return array_any($this->matchingSptVersions, fn (array $version): bool => ! $version['is_published']);
    }

    /**
     * Get only the unpublished SPT versions from the matching list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnpublishedSptVersions(): array
    {
        return array_filter($this->matchingSptVersions, fn (array $version): bool => ! $version['is_published']);
    }

    /**
     * Add a new dependency.
     */
    public function addDependency(): void
    {
        $uniqueId = uniqid();
        $this->dependencies[] = [
            'id' => $uniqueId,
            'modId' => '',
            'constraint' => '',
        ];
        $this->matchingDependencyVersions[count($this->dependencies) - 1] = [];
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
     * Update a dependency's mod ID.
     */
    public function updateDependencyModId(int $index, string $modId): void
    {
        if (isset($this->dependencies[$index])) {
            $this->dependencies[$index]['modId'] = $modId;
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update a dependency's constraint.
     */
    public function updateDependencyConstraint(int $index, string $constraint): void
    {
        if (isset($this->dependencies[$index])) {
            $this->dependencies[$index]['constraint'] = $constraint;
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Add a new VirusTotal link.
     */
    public function addVirusTotalLink(): void
    {
        $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
    }

    /**
     * Remove a VirusTotal link.
     */
    public function removeVirusTotalLink(int $index): void
    {
        unset($this->virusTotalLinks[$index]);
        $this->virusTotalLinks = array_values($this->virusTotalLinks);
    }

    /**
     * Update the matching versions for a dependency constraint.
     */
    public function updatedDependencies(mixed $value, ?string $property = null): void
    {
        if ($property === null) {
            return;
        }

        // Extract the index from the property path
        if (preg_match('/^(\d+)\.(constraint|modId)$/', $property, $matches)) {
            $index = (int) $matches[1];

            // Update matching versions when either modId or constraint changes
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Mount the component.
     */
    public function mount(Mod $mod): void
    {
        $this->mod = $mod;
        $this->honeypotData = new HoneypotData();
        $this->modGuid = $mod->guid ?? '';
        $this->modCategoryId = $mod->category_id;

        // Initialize with one empty VirusTotal link
        $this->virusTotalLinks = [['url' => '', 'label' => '']];

        // Pre-populate dependencies from the most recent version
        $this->populateDependenciesFromPreviousVersion();

        $this->authorize('create', [ModVersion::class, $this->mod]);
    }

    /**
     * Save the GUID to the mod without refreshing the page.
     */
    public function saveGuid(): void
    {
        $this->authorize('update', $this->mod);

        // Validate the GUID
        $this->validate(
            [
                'newModGuid' => 'required|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,'.$this->mod->id,
            ],
            [
                'newModGuid.required' => 'The mod GUID is required.',
                'newModGuid.regex' => 'The mod GUID must use reverse domain notation (e.g., com.username.modname) with only letters, numbers, hyphens, and dots.',
            ],
        );

        // Save the GUID to the mod
        $this->mod->guid = $this->newModGuid;
        $this->mod->save();

        // Update the component state
        $this->modGuid = $this->newModGuid;
        $this->guidSaved = true;

        // Show success message
        flash()->success('Mod GUID has been successfully saved.');
    }

    /**
     * Save the category to the mod without refreshing the page.
     */
    public function saveCategory(): void
    {
        $this->authorize('update', $this->mod);

        // Validate the category
        $this->validate(
            [
                'newModCategoryId' => 'required|exists:mod_categories,id',
            ],
            [
                'newModCategoryId.required' => 'Please select a category.',
                'newModCategoryId.exists' => 'The selected category is invalid.',
            ],
        );

        // Save the category to the mod
        $this->mod->category_id = (int) $this->newModCategoryId;
        $this->mod->save();

        // Update the component state
        $this->modCategoryId = (int) $this->newModCategoryId;
        $this->categorySaved = true;

        // Show success message
        flash()->success('Mod category has been successfully saved.');
    }

    /**
     * Get all mod categories ordered by title.
     *
     * @return Collection<int, ModCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ModCategory::cachedOrdered();
    }

    /**
     * Save the mod version.
     */
    public function save(): void
    {
        $this->authorize('create', [ModVersion::class, $this->mod]);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Additional validation for matching versions
        if (! $this->validateDependenciesHaveMatchingVersions()) {
            return;
        }

        // Combine date and time into a single published_at value, converting from user timezone to UTC.
        $publishedAt = null;
        if ($this->publishedAtDate !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $dateTimeString = $this->publishedAtDate.' '.($this->publishedAtTime ?? '00:00');
            $publishedAt = Date::parse($dateTimeString, $userTimezone)->setTimezone('UTC')->second(0)->toDateTimeString();
        }

        // Use a transaction to ensure both mod GUID and version are saved atomically
        /** @var array{version: string, description: string, link: string, sptVersionConstraint: string} $validated */
        $modVersion = DB::transaction(function () use ($validated, $publishedAt) {
            // Update the mod's GUID if needed (only if not already saved inline)
            if ($this->modGuidRequired && ($this->modGuid === '' || $this->modGuid === '0') && ($this->newModGuid !== '' && $this->newModGuid !== '0') && ! $this->guidSaved) {
                $this->mod->guid = $this->newModGuid;
                $this->mod->save();
            }

            // Create the mod version.
            $modVersion = $this->mod->versions()->create([
                'version' => $validated['version'],
                'description' => $validated['description'],
                'link' => $validated['link'],
                'content_length' => $this->downloadLinkRule?->contentLength,
                'spt_version_constraint' => $validated['sptVersionConstraint'],
                'fika_compatibility' => FikaCompatibility::from($this->fikaCompatibilityStatus),
                'published_at' => $publishedAt,
            ]);

            // Attach SPT versions with pinning information
            if ($this->matchingSptVersions !== []) {
                $sptVersions = SptVersion::query()
                    ->withoutGlobalScope(PublishedSptVersionScope::class)
                    ->whereIn('version', array_column($this->matchingSptVersions, 'version'))
                    ->get();

                $pivotData = [];
                foreach ($sptVersions as $sptVersion) {
                    $isPinned = false;

                    // Only pin if the user opted in AND the SPT version is unpublished
                    if ($this->pinToSptVersions && ! $sptVersion->is_published) {
                        $isPinned = true;
                    }

                    $pivotData[$sptVersion->id] = [
                        'pinned_to_spt_publish' => $isPinned,
                    ];
                }

                $modVersion->sptVersions()->sync($pivotData);
            }

            // Create dependencies if any were specified
            foreach ($this->dependencies as $dependency) {
                if (! empty($dependency['modId']) && ($dependency['constraint'] !== '' && $dependency['constraint'] !== '0')) {
                    // Skip self-dependencies
                    if ((int) $dependency['modId'] === $this->mod->id) {
                        continue;
                    }

                    $modVersion->dependencies()->create([
                        'dependent_mod_id' => $dependency['modId'],
                        'constraint' => $dependency['constraint'],
                    ]);
                }
            }

            // Create VirusTotal links if any were specified
            foreach ($this->virusTotalLinks as $virusTotalLink) {
                if (! empty($virusTotalLink['url'])) {
                    $modVersion->virusTotalLinks()->create([
                        'url' => $virusTotalLink['url'],
                        'label' => empty($virusTotalLink['label']) ? '' : $virusTotalLink['label'],
                    ]);
                }
            }

            return $modVersion;
        });

        Track::event(TrackingEventType::VERSION_CREATE, $modVersion);

        Session::flash('success', 'Mod version has been successfully created.');

        // Redirect to the mod version page.
        $this->redirect(route('mod.show', [$this->mod->id, $this->mod->slug]));
    }

    /**
     * Get custom validation rules for dependencies.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [];

        // Add mod GUID validation if required and mod doesn't have one and hasn't been saved already
        if ($this->modGuidRequired && ($this->modGuid === '' || $this->modGuid === '0') && ! $this->guidSaved) {
            $rules['newModGuid'] = ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/', 'unique:mods,guid'];
        }

        // Add mod category validation if mod doesn't have one and hasn't been saved already
        if (($this->modCategoryId === null || $this->modCategoryId === 0) && ! $this->categorySaved) {
            $rules['newModCategoryId'] = ['required', 'exists:mod_categories,id'];
        }

        foreach ($this->dependencies as $index => $dependency) {
            // If either field is filled, both are required
            if (! empty($dependency['modId']) || ! empty($dependency['constraint'])) {
                $rules[sprintf('dependencies.%d.modId', $index)] = 'required|exists:mods,id';
                $rules[sprintf('dependencies.%d.constraint', $index)] = ['required', 'string', new SemverConstraintRule()];
            } else {
                $rules[sprintf('dependencies.%d.modId', $index)] = 'nullable|exists:mods,id';
                $rules[sprintf('dependencies.%d.constraint', $index)] = ['nullable', 'string', new SemverConstraintRule()];
            }
        }

        // VirusTotal links validation
        $rules['virusTotalLinks'] = 'required|array|min:1';
        $rules['virusTotalLinks.*.url'] = 'required|string|url|starts_with:https://www.virustotal.com/';
        $rules['virusTotalLinks.*.label'] = 'nullable|string|max:255';

        // Download link validation
        $this->downloadLinkRule = new DirectDownloadLink();
        $rules['link'] = ['required', 'string', 'url', 'starts_with:https://,http://', $this->downloadLinkRule];

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $messages = [
            'newModGuid.required' => 'A mod GUID is required for versions compatible with SPT 4.0.0 or above.',
            'newModGuid.regex' => 'The mod GUID must use reverse domain notation (e.g., com.username.modname) with only lowercase letters, numbers, and dots.',
            'newModGuid.unique' => 'This mod GUID is already in use by another mod.',
            'newModCategoryId.required' => 'A category is required before publishing a mod version.',
            'newModCategoryId.exists' => 'The selected category is invalid.',
            'virusTotalLinks.required' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.min' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.*.url.required' => 'Please enter a valid VirusTotal URL.',
            'virusTotalLinks.*.url.url' => 'Please enter a valid URL (e.g., https://www.virustotal.com/...).',
            'virusTotalLinks.*.url.starts_with' => 'The URL must start with https://www.virustotal.com/',
            'virusTotalLinks.*.label.max' => 'The label must not exceed 255 characters.',
        ];

        foreach (array_keys($this->dependencies) as $index) {
            $messages[sprintf('dependencies.%d.modId.required', $index)] = 'Please select a mod.';
            $messages[sprintf('dependencies.%d.modId.exists', $index)] = 'The selected mod does not exist.';
            $messages[sprintf('dependencies.%d.constraint.required', $index)] = 'Please specify a version constraint.';
            $messages[sprintf('dependencies.%d.constraint.string', $index)] = 'This version constraint is invalid.';
        }

        foreach (array_keys($this->virusTotalLinks) as $index) {
            $messages[sprintf('virusTotalLinks.%d.url.required', $index)] = 'Please provide a VirusTotal URL.';
            $messages[sprintf('virusTotalLinks.%d.url.url', $index)] = 'Please provide a valid URL.';
            $messages[sprintf('virusTotalLinks.%d.url.starts_with', $index)] = 'The URL must be from VirusTotal (https://www.virustotal.com/).';
        }

        return $messages;
    }

    /**
     * Validate dependencies have matching versions.
     */
    protected function validateDependenciesHaveMatchingVersions(): bool
    {
        $hasErrors = false;

        foreach ($this->dependencies as $index => $dependency) {
            // Skip if both fields are empty (optional dependency)
            if (empty($dependency['modId']) && empty($dependency['constraint'])) {
                continue;
            }

            // Check if there are matching versions
            if (($dependency['modId'] !== '' && $dependency['modId'] !== '0') && ($dependency['constraint'] !== '' && $dependency['constraint'] !== '0') && (! isset($this->matchingDependencyVersions[$index]) || $this->matchingDependencyVersions[$index] === [])) {
                $this->addError(sprintf('dependencies.%d.constraint', $index), 'No matching versions found. Please adjust the version constraint.');
                $hasErrors = true;
            }
        }

        return ! $hasErrors;
    }

    /**
     * Populate dependencies from the most recent version of this mod.
     */
    private function populateDependenciesFromPreviousVersion(): void
    {
        // Get the most recent version (regardless of publish status) with its dependencies
        $previousVersion = $this->mod
            ->versions()
            ->withoutGlobalScope(PublishedScope::class)
            ->with('dependencies.dependentMod')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->first();

        if ($previousVersion === null || $previousVersion->dependencies->isEmpty()) {
            return;
        }

        foreach ($previousVersion->dependencies as $dependency) {
            $uniqueId = uniqid();
            $index = count($this->dependencies);

            $this->dependencies[] = [
                'id' => $uniqueId,
                'modId' => (string) $dependency->dependent_mod_id,
                'constraint' => $dependency->constraint,
            ];

            // Populate the matching versions for this dependency
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update the matching mod versions for a specific dependency.
     */
    private function updateMatchingDependencyVersions(int $index): void
    {
        if (! isset($this->dependencies[$index])) {
            return;
        }

        $dependency = $this->dependencies[$index];

        if (empty($dependency['modId']) || empty($dependency['constraint'])) {
            $this->matchingDependencyVersions[$index] = [];

            return;
        }

        try {
            $mod = Mod::query()->find($dependency['modId']);
            if (! $mod) {
                $this->matchingDependencyVersions[$index] = [];

                return;
            }

            $versions = $mod
                ->versions()
                ->withoutGlobalScope(PublishedScope::class)
                ->get()
                ->filter(fn (ModVersion $version): bool => Semver::satisfies($version->version, $dependency['constraint']))
                ->map(
                    fn (ModVersion $version): array => [
                        'id' => $version->id,
                        'mod_name' => $mod->name,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();

            $this->matchingDependencyVersions[$index] = $versions;
        } catch (Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }
};
