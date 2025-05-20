<?php

declare(strict_types=1);

namespace App\Livewire\Page\ModVersion;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use Carbon\Carbon;
use Composer\Semver\Semver;
use Exception;
use Illuminate\View\View;
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
     *
     * @var Mod
     */
    public Mod $mod;

    /**
     * The mod version being edited.
     *
     * @var ModVersion
     */
    public ModVersion $modVersion;

    /**
     * The version number.
     *
     * @var string
     */
    #[Validate(['required', 'string', 'max:50', new SemverRule])]
    public string $version = '';

    /**
     * The description of the mod version.
     *
     * @var string
     */
    #[Validate('required|string')]
    public string $description = '';

    /**
     * The link to the mod version.
     *
     * @var string
     */
    #[Validate('required|string|url|starts_with:https://,http://')]
    public string $link = '';

    /**
     * The SPT version constraint.
     *
     * @var string
     */
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule])]
    public string $sptVersionConstraint = '';

    /**
     * The link to the virus total scan of the mod version.
     *
     * @var string
     */
    #[Validate('required|string|url|starts_with:https://www.virustotal.com/')]
    public string $virusTotalLink = '';

    /**
     * The published at date of the mod version.
     *
     * @var string|null
     */
    #[Validate('nullable|date')]
    public ?string $publishedAt = null;

    /**
     * The matching SPT versions for the current constraint.
     *
     * @var array<int, MatchingSptVersion>
     */
    public array $matchingSptVersions = [];

    /**
     * Mount the component.
     *
     * @param Mod $mod
     * @param ModVersion $modVersion
     * @return void
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
        $this->publishedAt = $modVersion->published_at ? Carbon::parse($modVersion->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->toDateTimeString() : null;

        $this->updatedSptVersionConstraint();
    }

    /**
     * Update the matching SPT versions when the constraint changes.
     *
     * @return void
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
     * Save the mod version changes.
     *
     * @return void
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

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Carbon::parse($this->publishedAt, $userTimezone)->setTimezone('UTC');
        }

        $this->modVersion->version = $validated['version'];
        $this->modVersion->description = $validated['description'];
        $this->modVersion->link = $validated['link'];
        $this->modVersion->spt_version_constraint = $validated['sptVersionConstraint'];
        $this->modVersion->virus_total_link = $validated['virusTotalLink'];
        $this->modVersion->published_at = $publishedAtCarbon;

        $this->modVersion->save();

        flash()->success('Mod version has been successfully updated.');

        $this->redirect($this->modVersion->mod->detail_url);
    }

    /**
     * Render the component.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.page.mod-version.edit');
    }
}