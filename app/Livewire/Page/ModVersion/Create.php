<?php

declare(strict_types=1);

namespace App\Livewire\Page\ModVersion;

use App\Enums\TrackingEventType;
use App\Facades\Track;
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
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * @property-read bool $modGuidRequired
 * @property string $newModGuid
 */
class Create extends Component
{
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
    #[Validate(['required', 'string', 'max:50', new SemverRule])]
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
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule])]
    public string $sptVersionConstraint = '';

    /**
     * The link to the virus total scan of the mod version.
     */
    #[Validate('required|string|url|starts_with:https://www.virustotal.com/')]
    public string $virusTotalLink = '';

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
                ->map(fn (SptVersion $version): array => [
                    'version' => $version->version,
                    'color_class' => $version->color_class,
                    'is_published' => ! is_null($version->publish_date) && $version->publish_date->lte(now()),
                    'publish_date' => $version->publish_date?->format('Y-m-d H:i:s'),
                ])
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
        $this->honeypotData = new HoneypotData;
        $this->modGuid = $mod->guid ?? '';

        $this->authorize('create', [ModVersion::class, $this->mod]);
    }

    /**
     * Save the GUID to the mod without refreshing the page.
     */
    public function saveGuid(): void
    {
        $this->authorize('update', $this->mod);

        // Validate the GUID
        $this->validate([
            'newModGuid' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/', 'unique:mods,guid'],
        ], [
            'newModGuid.required' => 'The mod GUID is required.',
            'newModGuid.regex' => 'The mod GUID must use reverse domain notation (e.g., com.username.modname) with only lowercase letters, numbers, and dots.',
            'newModGuid.unique' => 'This mod GUID is already in use by another mod.',
        ]);

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

        // Parse the published at date in the user's timezone, falling back to UTC if the user has no timezone, and
        // convert it to UTC for DB storage. Zero out seconds for consistency with datetime-local input format.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Date::parse($this->publishedAt, $userTimezone)
                ->setTimezone('UTC')
                ->second(0)
                ->toDateTimeString();
        }

        // Use a transaction to ensure both mod GUID and version are saved atomically
        $modVersion = DB::transaction(function () use ($validated) {
            // Update the mod's GUID if needed (only if not already saved inline)
            if ($this->modGuidRequired && empty($this->modGuid) && ! empty($this->newModGuid) && ! $this->guidSaved) {
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
                'virus_total_link' => $validated['virusTotalLink'],
                'published_at' => $this->publishedAt,
            ]);

            // Attach SPT versions with pinning information
            if (! empty($this->matchingSptVersions)) {
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
                if (! empty($dependency['modId']) && ! empty($dependency['constraint'])) {
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

            return $modVersion;
        });

        Track::event(TrackingEventType::VERSION_CREATE, $modVersion);

        Session::flash('success', 'Mod version has been successfully created.');

        // Redirect to the mod version page.
        $this->redirect(route('mod.show', [$this->mod->id, $this->mod->slug]));
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod-version.create');
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
        if ($this->modGuidRequired && empty($this->modGuid) && ! $this->guidSaved) {
            $rules['newModGuid'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/', 'unique:mods,guid'];
        }

        foreach ($this->dependencies as $index => $dependency) {
            // If either field is filled, both are required
            if (! empty($dependency['modId']) || ! empty($dependency['constraint'])) {
                $rules[sprintf('dependencies.%d.modId', $index)] = 'required|exists:mods,id';
                $rules[sprintf('dependencies.%d.constraint', $index)] = ['required', 'string', new SemverConstraintRule];
            } else {
                $rules[sprintf('dependencies.%d.modId', $index)] = 'nullable|exists:mods,id';
                $rules[sprintf('dependencies.%d.constraint', $index)] = ['nullable', 'string', new SemverConstraintRule];
            }
        }

        // Download link validation
        $this->downloadLinkRule = new DirectDownloadLink;
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
        ];

        foreach ($this->dependencies as $index => $dependency) {
            $messages[sprintf('dependencies.%d.modId.required', $index)] = 'Please select a mod.';
            $messages[sprintf('dependencies.%d.modId.exists', $index)] = 'The selected mod does not exist.';
            $messages[sprintf('dependencies.%d.constraint.required', $index)] = 'Please specify a version constraint.';
            $messages[sprintf('dependencies.%d.constraint.string', $index)] = 'This version constraint is invalid.';
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
            if (! empty($dependency['modId']) && ! empty($dependency['constraint'])) {
                if (! isset($this->matchingDependencyVersions[$index]) || count($this->matchingDependencyVersions[$index]) === 0) {
                    $this->addError(sprintf('dependencies.%d.constraint', $index), 'No matching versions found. Please adjust the version constraint.');
                    $hasErrors = true;
                }
            }
        }

        return ! $hasErrors;
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

            $versions = $mod->versions()
                ->withoutGlobalScope(PublishedScope::class)
                ->get()
                ->filter(fn (ModVersion $version): bool => Semver::satisfies($version->version, $dependency['constraint']))
                ->map(fn (ModVersion $version): array => [
                    'id' => $version->id,
                    'mod_name' => $mod->name,
                    'version' => $version->version,
                ])
                ->values()
                ->all();

            $this->matchingDependencyVersions[$index] = $versions;
        } catch (Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }
}
