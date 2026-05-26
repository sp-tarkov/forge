<?php

declare(strict_types=1);

namespace App\View\Components\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * A compact mod row used inside mod list cards, with SPT and dependency badges.
 */
final class ListRow extends Component
{
    /**
     * The mod version whose details (version string, SPT badge) are displayed.
     */
    public ?ModVersion $version;

    /**
     * The SPT version to render on the card badge. Set by callers (e.g. the
     * list show page) when the card sits inside a list with a target SPT and
     * the resolved version is an exact compatibility match, so the badge
     * shows the curator's target SPT even when the resolved version also
     * supports newer SPTs. Falls back to the version's own `latestSptVersion`
     * when null.
     */
    public ?SptVersion $sptBadge;

    /**
     * @param  Collection<int, ModVersion>|null  $dependencyVersions  Resolved dependency versions for the mod.
     * @param  Collection<int, int>|null  $listModIds  Mod ids already present on the list, used to flag missing dependencies.
     */
    public function __construct(
        public Mod $mod,
        ?ModVersion $version = null,
        public ?Collection $dependencyVersions = null,
        public bool $isDependency = false,
        public bool $incompatible = false,
        ?SptVersion $displaySptVersion = null,
        public ?Collection $listModIds = null,
        public ?string $wireKey = null,
    ) {
        $this->version = $version ?? $mod->latestVersion;
        $this->sptBadge = $displaySptVersion ?? $this->version?->latestSptVersion;
    }

    /**
     * Whether the row should render an SPT compatibility badge.
     */
    public function hasSptBadge(): bool
    {
        return $this->sptBadge instanceof SptVersion
            || ($this->version instanceof ModVersion && $this->version->spt_version_constraint === '');
    }

    /**
     * Whether the row should render a dependency badge.
     */
    public function hasDependencyBadge(): bool
    {
        return $this->dependencyVersions instanceof Collection && $this->dependencyVersions->isNotEmpty();
    }

    /**
     * Whether the row should render the "not compatible with list SPT
     * version" indicator. True only when the parent list has a target SPT
     * version that the resolved version does not match.
     */
    public function showIncompatibleIndicator(): bool
    {
        return $this->incompatible;
    }

    /**
     * Dependency versions not yet satisfied by a mod already on the list.
     *
     * @return Collection<int, ModVersion>
     */
    public function missingDependencies(): Collection
    {
        if (! $this->dependencyVersions instanceof Collection) {
            return new Collection;
        }

        if (! $this->listModIds instanceof Collection) {
            return $this->dependencyVersions;
        }

        return $this->dependencyVersions
            ->reject(fn (ModVersion $depVersion): bool => $this->listModIds->contains($depVersion->mod_id))
            ->values();
    }

    /**
     * Whether every resolved dependency is present on the list.
     */
    public function dependenciesSatisfied(): bool
    {
        return $this->missingDependencies()->isEmpty();
    }

    /**
     * The label shown on the dependency badge.
     */
    public function dependencyBadgeLabel(): string
    {
        $missing = $this->missingDependencies();

        if ($missing->isEmpty()) {
            $count = $this->dependencyVersions?->count() ?? 0;

            return trans_choice(':count dependency satisfied|:count dependencies satisfied', $count, ['count' => $count]);
        }

        return trans_choice(':count missing dependency|:count missing dependencies', $missing->count(), ['count' => $missing->count()]);
    }

    /**
     * Whether a dependency mod is already present on the list.
     */
    public function dependencyOnList(ModVersion $depVersion): bool
    {
        return $this->listModIds instanceof Collection && $this->listModIds->contains($depVersion->mod_id);
    }

    /**
     * Get the view that represents the component.
     */
    public function render(): View
    {
        return view('components.mod.list-row');
    }
}
