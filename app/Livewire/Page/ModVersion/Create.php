<?php

declare(strict_types=1);

namespace App\Livewire\Page\ModVersion;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use App\Support\Version;
use Carbon\Carbon;
use Composer\Semver\Semver;
use Exception;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Create extends Component
{
    /**
     * The mod to create the version for.
     */
    public Mod $mod;

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
    #[Validate('required|string|url|starts_with:https://,http://')]
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
     * @var array<int, array{version: string, color_class: string}>
     */
    public array $matchingSptVersions = [];

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
     * Mount the component.
     */
    public function mount(Mod $mod): void
    {
        $this->mod = $mod;
        $this->authorize('create', [ModVersion::class, $this->mod]);
    }

    /**
     * Save the mod version.
     */
    public function save(): void
    {
        $this->authorize('create', [ModVersion::class, $this->mod]);

        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Parse the published at date in the user's timezone, falling back to UTC if the user has no timezone, and
        // convert it to UTC for DB storage.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Carbon::parse($this->publishedAt, $userTimezone)
                ->setTimezone('UTC')
                ->toDateTimeString();
        }

        // Create the mod version.
        $this->mod->versions()->create([
            'version' => $validated['version'],
            'description' => $validated['description'],
            'link' => $validated['link'],
            'spt_version_constraint' => $validated['sptVersionConstraint'],
            'virus_total_link' => $validated['virusTotalLink'],
            'published_at' => $this->publishedAt,
        ]);

        flash()->success('Mod version has been successfully created.');

        // Redirect to the mod version page.
        $this->redirect(route('mod.show', [$this->mod->id, $this->mod->slug]));
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.page.mod-version.create');
    }
}
