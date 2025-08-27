<?php

declare(strict_types=1);

namespace App\Livewire\Page\ModVersion;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\Scopes\PublishedScope;
use App\Models\SptVersion;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class Edit extends Component
{
    use UsesSpamProtection;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The mod being edited.
     */
    public Mod $mod;

    /**
     * The mod version being edited.
     */
    public ModVersion $modVersion;

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
     * The DirectDownloadLink rule instance (for content length extraction).
     */
    private ?DirectDownloadLink $downloadLinkRule = null;

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
     * @var array<int, array{version: string, color_class: string}>
     */
    public array $matchingSptVersions = [];

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
     * Mount the component.
     */
    public function mount(Mod $mod, ModVersion $modVersion): void
    {
        $this->honeypotData = new HoneypotData;

        $this->mod = $mod;
        $this->modVersion = $modVersion;

        $this->authorize('update', [$this->modVersion, $this->mod]);

        // Prefill fields from the mod version
        $this->version = $modVersion->version;
        $this->description = $modVersion->description;
        $this->link = $modVersion->link;
        $this->sptVersionConstraint = $modVersion->spt_version_constraint;
        $this->virusTotalLink = $modVersion->virus_total_link;
        $this->publishedAt = $modVersion->published_at ? Carbon::parse($modVersion->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d\TH:i') : null;

        // Load existing dependencies
        $this->loadExistingDependencies();

        $this->updatedSptVersionConstraint();
    }

    /**
     * Load existing dependencies from the database.
     */
    private function loadExistingDependencies(): void
    {
        $this->dependencies = [];
        $this->matchingDependencyVersions = [];

        foreach ($this->modVersion->dependencies as $index => $dependency) {
            $this->dependencies[] = [
                'id' => uniqid(),
                'modId' => (string) $dependency->dependent_mod_id,
                'constraint' => $dependency->constraint,
            ];
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Get custom validation rules for dependencies.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [];
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
        $messages = [];
        foreach ($this->dependencies as $index => $dependency) {
            $messages[sprintf('dependencies.%d.modId.required', $index)] = 'Please select a mod.';
            $messages[sprintf('dependencies.%d.modId.exists', $index)] = 'The selected mod does not exist.';
            $messages[sprintf('dependencies.%d.constraint.required', $index)] = 'Please specify a version constraint.';
            $messages[sprintf('dependencies.%d.constraint.string', $index)] = 'This version constraint is invalid.';
        }

        return $messages;
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
                ->toArray();

            $this->matchingDependencyVersions[$index] = $versions;
        } catch (Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }

    /**
     * Update the matching SPT versions when the constraint changes.
     */
    public function updatedSptVersionConstraint(): void
    {
        if (empty($this->sptVersionConstraint)) {
            $this->matchingSptVersions = [];

            return;
        }

        try {
            $validSptVersions = SptVersion::allValidVersions();
            $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $this->sptVersionConstraint);

            $this->matchingSptVersions = SptVersion::query()
                ->whereIn('version', $compatibleSptVersions)
                ->select(['version', 'color_class'])
                ->orderByDesc('version_major')
                ->orderByDesc('version_minor')
                ->orderByDesc('version_patch')
                ->orderBy('version_labels')
                ->get()
                ->map(fn (SptVersion $version): array => [
                    'version' => $version->version,
                    'color_class' => $version->color_class,
                ])
                ->toArray();
        } catch (Exception) {
            $this->matchingSptVersions = [];
        }
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
     * Save the mod version changes.
     */
    public function save(): void
    {
        $this->authorize('update', [$this->modVersion, $this->mod]);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Additional validation for matching versions
        if (! $this->validateDependenciesHaveMatchingVersions()) {
            return;
        }

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        // Zero out seconds for consistency with datetime-local input format.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Carbon::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        $this->modVersion->version = $validated['version'];
        $this->modVersion->description = $validated['description'];
        $this->modVersion->link = $validated['link'];
        $this->modVersion->content_length = $this->downloadLinkRule?->contentLength;
        $this->modVersion->spt_version_constraint = $validated['sptVersionConstraint'];
        $this->modVersion->virus_total_link = $validated['virusTotalLink'];
        $this->modVersion->published_at = $publishedAtCarbon;

        $this->modVersion->save();

        // Update dependencies
        // First, remove all existing dependencies
        $this->modVersion->dependencies()->delete();

        // Then create new dependencies
        foreach ($this->dependencies as $dependency) {
            if (! empty($dependency['modId']) && ! empty($dependency['constraint'])) {
                // Skip self-dependencies
                if ($dependency['modId'] == $this->mod->id) {
                    continue;
                }

                $this->modVersion->dependencies()->create([
                    'dependent_mod_id' => $dependency['modId'],
                    'constraint' => $dependency['constraint'],
                ]);
            }
        }

        Track::event(TrackingEventType::VERSION_EDIT, $this->modVersion);

        flash()->success('Mod version has been successfully updated.');

        $this->redirect($this->modVersion->mod->detail_url);
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod-version.edit');
    }
}
