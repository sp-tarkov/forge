<?php

declare(strict_types=1);

use App\Enums\FikaCompatibility;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\Scopes\PublishedScope;
use App\Models\Scopes\PublishedSptVersionScope;
use App\Models\SptVersion;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use App\Support\Version;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * @property-read bool $modGuidRequired
 * @property string $newModGuid
 */
new #[Layout('layouts::base')] class extends Component {
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
    public ?string $publishedAt = null;

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
        if (empty($this->sptVersionConstraint)) {
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
        } catch (\Exception) {
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

        if (empty($this->sptVersionConstraint)) {
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
                    fn(SptVersion $version): array => [
                        'version' => $version->version,
                        'color_class' => $version->color_class,
                        'is_published' => !is_null($version->publish_date) && $version->publish_date->lte(now()),
                        'publish_date' => $version->publish_date?->format('Y-m-d H:i:s'),
                    ],
                )
                ->all();
        } catch (\Exception) {
            $this->matchingSptVersions = [];
        }
    }

    /**
     * Check if there are any unpublished SPT versions in the matching list.
     */
    public function hasUnpublishedSptVersions(): bool
    {
        return array_any($this->matchingSptVersions, fn(array $version): bool => !$version['is_published']);
    }

    /**
     * Get only the unpublished SPT versions from the matching list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnpublishedSptVersions(): array
    {
        return array_filter($this->matchingSptVersions, fn(array $version): bool => !$version['is_published']);
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
     * Save the GUID to the mod without refreshing the page.
     */
    public function saveGuid(): void
    {
        $this->authorize('update', $this->mod);

        // Validate the GUID
        $this->validate(
            [
                'newModGuid' => 'required|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,' . $this->mod->id,
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
     * Save the mod version.
     */
    public function save(): void
    {
        $this->authorize('create', [ModVersion::class, $this->mod]);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Validate the form.
        $validated = $this->validate();
        if (!$validated) {
            return;
        }

        // Additional validation for matching versions
        if (!$this->validateDependenciesHaveMatchingVersions()) {
            return;
        }

        // Parse the published at date in the user's timezone, falling back to UTC if the user has no timezone, and
        // convert it to UTC for DB storage. Zero out seconds for consistency with datetime-local input format.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Date::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0)->toDateTimeString();
        }

        // Use a transaction to ensure both mod GUID and version are saved atomically
        $modVersion = DB::transaction(function () use ($validated) {
            // Update the mod's GUID if needed (only if not already saved inline)
            if ($this->modGuidRequired && empty($this->modGuid) && !empty($this->newModGuid) && !$this->guidSaved) {
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
                'published_at' => $this->publishedAt,
            ]);

            // Attach SPT versions with pinning information
            if (!empty($this->matchingSptVersions)) {
                $sptVersions = SptVersion::query()
                    ->withoutGlobalScope(PublishedSptVersionScope::class)
                    ->whereIn('version', array_column($this->matchingSptVersions, 'version'))
                    ->get();

                $pivotData = [];
                foreach ($sptVersions as $sptVersion) {
                    $isPinned = false;

                    // Only pin if the user opted in AND the SPT version is unpublished
                    if ($this->pinToSptVersions && !$sptVersion->is_published) {
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
                if (!empty($dependency['modId']) && !empty($dependency['constraint'])) {
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
                if (!empty($virusTotalLink['url'])) {
                    $modVersion->virusTotalLinks()->create([
                        'url' => $virusTotalLink['url'],
                        'label' => !empty($virusTotalLink['label']) ? $virusTotalLink['label'] : '',
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
        if ($this->modGuidRequired && empty($this->modGuid) && !$this->guidSaved) {
            $rules['newModGuid'] = ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/', 'unique:mods,guid'];
        }

        // Add mod category validation if mod doesn't have one and hasn't been saved already
        if (empty($this->modCategoryId) && !$this->categorySaved) {
            $rules['newModCategoryId'] = ['required', 'exists:mod_categories,id'];
        }

        foreach ($this->dependencies as $index => $dependency) {
            // If either field is filled, both are required
            if (!empty($dependency['modId']) || !empty($dependency['constraint'])) {
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

        foreach ($this->dependencies as $index => $dependency) {
            $messages[sprintf('dependencies.%d.modId.required', $index)] = 'Please select a mod.';
            $messages[sprintf('dependencies.%d.modId.exists', $index)] = 'The selected mod does not exist.';
            $messages[sprintf('dependencies.%d.constraint.required', $index)] = 'Please specify a version constraint.';
            $messages[sprintf('dependencies.%d.constraint.string', $index)] = 'This version constraint is invalid.';
        }

        foreach ($this->virusTotalLinks as $index => $virusTotalLink) {
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
            if (!empty($dependency['modId']) && !empty($dependency['constraint'])) {
                if (!isset($this->matchingDependencyVersions[$index]) || count($this->matchingDependencyVersions[$index]) === 0) {
                    $this->addError(sprintf('dependencies.%d.constraint', $index), 'No matching versions found. Please adjust the version constraint.');
                    $hasErrors = true;
                }
            }
        }

        return !$hasErrors;
    }

    /**
     * Update the matching mod versions for a specific dependency.
     */
    private function updateMatchingDependencyVersions(int $index): void
    {
        if (!isset($this->dependencies[$index])) {
            return;
        }

        $dependency = $this->dependencies[$index];

        if (empty($dependency['modId']) || empty($dependency['constraint'])) {
            $this->matchingDependencyVersions[$index] = [];

            return;
        }

        try {
            $mod = Mod::query()->find($dependency['modId']);
            if (!$mod) {
                $this->matchingDependencyVersions[$index] = [];

                return;
            }

            $versions = $mod
                ->versions()
                ->withoutGlobalScope(PublishedScope::class)
                ->get()
                ->filter(fn(ModVersion $version): bool => Semver::satisfies($version->version, $dependency['constraint']))
                ->map(
                    fn(ModVersion $version): array => [
                        'id' => $version->id,
                        'mod_name' => $mod->name,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();

            $this->matchingDependencyVersions[$index] = $versions;
        } catch (\Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }
};
?>

<x-slot:title>
    {!! __('Create a New Version for :mod - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Create a new version for :mod to share with the community.', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.cube-transparent class="w-5 h-5" />
        {{ __('Create Mod Version') }}: {{ $mod->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Version Information</h3>
                    <p class="my-2 text-sm/6 text-gray-600 dark:text-gray-400">Add a new version to your mod by filling
                        out this form. It will be unpublished by default.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a
                            href="{{ route('static.community-standards') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Community Standards</a>
                        and the <a
                            href="{{ route('static.content-guidelines') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Content Guidelines</a>.
                        Failing to do so will result in your mod being removed from the Forge and possible action being
                        taken against your account.
                    </p>
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-6">
                            @csrf

                            @if (empty($modCategoryId))
                                <div class="col-span-6">
                                    <flux:callout
                                        icon="information-circle"
                                        color="cyan"
                                    >
                                        <flux:callout.text>
                                            <div class="space-y-3">
                                                <div>
                                                    <strong>{{ __('Mod Category Required') }}</strong>
                                                    <p class="mt-1 text-sm">
                                                        {{ __('This mod does not have a category set. Please select a category that best describes your mod. This helps users find your mod more easily.') }}
                                                    </p>
                                                </div>
                                                <div class="flex gap-3 items-start">
                                                    <div class="flex-1">
                                                        <flux:select
                                                            wire:model.live="newModCategoryId"
                                                            placeholder="Choose category..."
                                                        >
                                                            @foreach (\App\Models\ModCategory::orderBy('title')->get() as $category)
                                                                <flux:select.option value="{{ $category->id }}">
                                                                    {{ $category->title }}
                                                                </flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                    <flux:button
                                                        variant="primary"
                                                        size="sm"
                                                        wire:click="saveCategory"
                                                        wire:loading.attr="disabled"
                                                        type="button"
                                                        class="mt-1"
                                                    >
                                                        <span
                                                            wire:loading.remove
                                                            wire:target="saveCategory"
                                                        >{{ __('Save Category') }}</span>
                                                        <span
                                                            wire:loading
                                                            wire:target="saveCategory"
                                                        >{{ __('Saving...') }}</span>
                                                    </flux:button>
                                                </div>
                                                <flux:error name="newModCategoryId" />
                                            </div>
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                            @endif

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Version Number') }}</flux:label>
                                <flux:description>{!! __('The version number for this release. Must follow semantic versioning.') !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="version"
                                    placeholder="1.0.0"
                                    maxlength="50"
                                />
                                <flux:error name="version" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="description"
                                    name="description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain what\'s new or changed in this version. Use markdown for formatting.',
                                    )"
                                    placeholder="This version includes updates to the..."
                                    rows="6"
                                    purify-config="description"
                                />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Download Link') }}</flux:label>
                                <flux:description>
                                    {{ __('Provide a direct download link to the mod file. The mod archive must follow the structure specified in the file submission guidelines or the launcher will not support automatic installs or updates for your mod.') }}
                                </flux:description>
                                <flux:input
                                    type="url"
                                    wire:model.blur="link"
                                    placeholder="https://www.example.com/your-mod-archive.7zip"
                                />
                                <flux:error name="link" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('SPT Version Constraint') }}</flux:label>
                                <flux:description>{!! __(
                                    'Specify which SPT versions this mod version is compatible with using semantic version constraints. For example, you can use the value ~3.11.0 to match all 3.11 versions. Works just like Composer or NPM. Start typing to see matches below.',
                                ) !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.live.debounce="sptVersionConstraint"
                                    placeholder="~3.11.0"
                                />
                                <flux:error name="sptVersionConstraint" />
                                @if (count($matchingSptVersions) > 0)
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ __('Current Matching SPT Versions:') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($matchingSptVersions as $version)
                                                <span
                                                    class="badge-version {{ $version['color_class'] }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                                >
                                                    {{ $version['version'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:field>

                            @if ($this->modGuidRequired && empty($modGuid))
                                <div class="col-span-6">
                                    <flux:callout
                                        icon="information-circle"
                                        color="amber"
                                    >
                                        <flux:callout.text>
                                            <div class="space-y-3">
                                                <div>
                                                    <strong>{{ __('Mod GUID Required') }}</strong>
                                                    <p class="mt-1 text-sm">
                                                        {{ __('This mod version targets SPT 4.0.0 or above, which requires a mod GUID. Enter a unique identifier for your mod in reverse domain notation. This GUID will be saved to the mod and should match the one in your mod files.') }}
                                                        {!! __(
                                                            'Please see the <a href=":url" target="_blank" class="underline hover:no-underline">Content Guidelines</a> for more information.',
                                                            ['url' => route('static.content-guidelines') . '#mod-types-requirements'],
                                                        ) !!}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex gap-3 items-start"
                                                    x-data="{ count: 0, text: '' }"
                                                >
                                                    <div class="flex-1">
                                                        <flux:input
                                                            type="text"
                                                            wire:model.live="newModGuid"
                                                            maxlength="255"
                                                            x-model="text"
                                                            @input="count = text.length"
                                                            placeholder="com.username.modname"
                                                        />
                                                        <div
                                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                                            x-text="`Max Length: ${count}/255`"
                                                        ></div>
                                                    </div>
                                                    <flux:button
                                                        variant="primary"
                                                        size="sm"
                                                        wire:click="saveGuid"
                                                        wire:loading.attr="disabled"
                                                        type="button"
                                                        class="mt-1"
                                                    >
                                                        <span
                                                            wire:loading.remove
                                                            wire:target="saveGuid"
                                                        >{{ __('Save GUID') }}</span>
                                                        <span
                                                            wire:loading
                                                            wire:target="saveGuid"
                                                        >{{ __('Saving...') }}</span>
                                                    </flux:button>
                                                </div>
                                                <flux:error name="newModGuid" />
                                            </div>
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                            @endif

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('VirusTotal Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the <a href="https://www.virustotal.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">VirusTotal</a> scan results for your mod files. This helps users verify the safety of your mod. At least one link is required.',
                                ) !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach ($virusTotalLinks as $index => $virusTotalLink)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.url"
                                                    placeholder="https://www.virustotal.com/..."
                                                />
                                            </div>
                                            <div class="w-40">
                                                <flux:input
                                                    type="text"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.label"
                                                    placeholder="Label (optional)"
                                                />
                                            </div>
                                            @if ($index > 0)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="removeVirusTotalLink({{ $index }})"
                                                    type="button"
                                                    icon="x-mark"
                                                />
                                            @endif
                                        </div>
                                        @error('virusTotalLinks.' . $index . '.url')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    @endforeach

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="addVirusTotalLink"
                                        type="button"
                                        icon="plus"
                                    >
                                        {{ __('Add Link') }}
                                    </flux:button>
                                </div>

                                <flux:error name="virusTotalLinks" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Fika Compatibility') }}</flux:label>
                                <flux:description>{{ __('Specify whether this mod version is compatible with Fika.') }}
                                </flux:description>
                                <flux:select wire:model.blur="fikaCompatibilityStatus">
                                    <option value="compatible">{{ __('Compatible') }}</option>
                                    <option value="incompatible">{{ __('Incompatible') }}</option>
                                    <option value="unknown">{{ __('Compatibility Unknown') }}</option>
                                </flux:select>
                                <flux:error name="fikaCompatibilityStatus" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Mod Dependencies') }}</flux:label>
                                <flux:description>
                                    {{ __('Specify other mods that this version depends on. Use semantic version constraints to define compatible versions.') }}
                                </flux:description>

                                <div class="space-y-4">
                                    @foreach ($dependencies as $index => $dependency)
                                        <div
                                            wire:key="dependency-{{ $dependency['id'] ?? $index }}"
                                            class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                                        >
                                            <div class="flex justify-between items-start mb-3">
                                                <span
                                                    class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Dependency #:num', ['num' => $index + 1]) }}</span>
                                                <flux:button
                                                    size="xs"
                                                    variant="outline"
                                                    wire:click="removeDependency({{ $index }})"
                                                    type="button"
                                                >
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <flux:field>
                                                    <flux:label>{{ __('Mod') }}</flux:label>
                                                    <livewire:form.mod-autocomplete
                                                        :key="'autocomplete-' . ($dependency['id'] ?? $index)"
                                                        :exclude-mod-id="$mod->id"
                                                        :selected-mod-id="$dependencies[$index]['modId'] ?? ''"
                                                        placeholder="Type to search for a mod..."
                                                        label="Select dependency mod"
                                                        @mod-selected="updateDependencyModId({{ $index }}, $event.detail.modId)"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.modId" />
                                                </flux:field>

                                                <flux:field>
                                                    <flux:label>{{ __('Version Constraint') }}</flux:label>
                                                    <flux:input
                                                        type="text"
                                                        wire:model.live.debounce="dependencies.{{ $index }}.constraint"
                                                        placeholder="~1.0.0"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.constraint" />
                                                </flux:field>
                                            </div>

                                            @if (isset($matchingDependencyVersions[$index]) && count($matchingDependencyVersions[$index]) > 0)
                                                <div class="mt-3">
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                        {{ __('Matching Versions:') }}</p>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($matchingDependencyVersions[$index] as $version)
                                                            <span
                                                                class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400"
                                                            >
                                                                {{ $version['mod_name'] }} v{{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif(!empty($dependencies[$index]['modId']) && !empty($dependencies[$index]['constraint']))
                                                <div class="mt-3">
                                                    <p class="text-sm text-yellow-600 dark:text-yellow-400">
                                                        {{ __('No matching versions found for this constraint.') }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        wire:click="addDependency"
                                        type="button"
                                    >
                                        {{ __('+ Add Dependency') }}
                                    </flux:button>
                                </div>
                            </flux:field>

                            <flux:field
                                class="col-span-6"
                                x-data="{
                                    now() {
                                            // Format: YYYY-MM-DDTHH:MM
                                            const pad = n => n.toString().padStart(2, '0');
                                            const d = new Date();
                                            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                        },
                                        get pinToSpt() {
                                            return $wire.pinToSptVersions;
                                        },
                                        hasUnpublished: {{ $this->hasUnpublishedSptVersions() ? 'true' : 'false' }}
                                }"
                            >
                                <flux:label badge="Optional">{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    @if ($this->hasUnpublishedSptVersions())
                                        {!! __(
                                            'Choose when to publish this mod version. You can either set a specific date or pin it to automatically publish when all of the unpublished SPT versions it supports are released.',
                                        ) !!}
                                    @else
                                        {!! __(
                                            'Select the date and time the mod will be published. If the mod is not published, it will not be discoverable by other users. Leave blank to keep the mod unpublished.',
                                        ) !!}
                                    @endif
                                </flux:description>

                                {{-- Pin to SPT version option (first) --}}
                                @if ($this->hasUnpublishedSptVersions())
                                    <div class="space-y-3">
                                        <label class="flex items-start gap-3">
                                            <flux:checkbox
                                                wire:model.live="pinToSptVersions"
                                                @change="if($event.target.checked) { $wire.set('publishedAt', null) }"
                                                class="mt-0.5"
                                            />
                                            <div class="flex-1">
                                                <div class="flex items-center flex-wrap gap-x-2 gap-y-1">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ __('Pin to unpublished SPT version publish dates:') }}
                                                    </span>

                                                    {{-- Show unpublished SPT versions inline --}}
                                                    @if (count($this->getUnpublishedSptVersions()) > 0)
                                                        @foreach ($this->getUnpublishedSptVersions() as $version)
                                                            <span
                                                                class="badge-version {{ $version['color_class'] }} inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium text-nowrap"
                                                            >
                                                                {{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ __('When enabled, this mod version will automatically publish when the unpublished SPT versions it supports are released.') }}
                                                    <span
                                                        class="text-orange-600 dark:text-amber-400 font-medium">{{ __('Note: SPT versions can be released at any time, so only use this option if your mod version is fully ready for release.') }}</span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    {{-- Separator (also hidden when pin is checked) --}}
                                    <div
                                        x-show="!pinToSpt"
                                        x-transition
                                    >
                                        <flux:separator
                                            text="or"
                                            class="my-4"
                                        />
                                    </div>
                                @endif

                                {{-- Manual publish date option (hidden when pin is checked) --}}
                                <div
                                    x-show="!pinToSpt"
                                    x-transition
                                >
                                    <div class="space-y-3">
                                        @if (auth()->user()->timezone === null)
                                            <flux:callout
                                                icon="exclamation-triangle"
                                                color="orange"
                                                inline="inline"
                                            >
                                                <flux:callout.text>
                                                    You have not selected a timezone for your account. You may continue,
                                                    but the published date will be interpreted as a UTC date.
                                                    Alternatively, you can <a
                                                        href="/user/profile"
                                                        class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                                                    >edit your profile</a> to set a specific timezone.
                                                </flux:callout.text>
                                            </flux:callout>
                                        @else
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                            </p>
                                        @endif
                                        <div class="flex gap-2 items-center">
                                            <flux:input
                                                type="datetime-local"
                                                wire:model.defer="publishedAt"
                                                placeholder="Leave blank to keep unpublished"
                                            />
                                            @if (auth()->user()->timezone !== null)
                                                <flux:button
                                                    size="sm"
                                                    variant="outline"
                                                    @click="$wire.set('publishedAt', now())"
                                                >Now</flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <flux:error name="publishedAt" />
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                            type="submit"
                        >{{ __('Create Version') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
