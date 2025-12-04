<?php

declare(strict_types=1);

namespace App\Livewire\Mod\Show;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class AddonsTab extends Component
{
    use WithPagination;

    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * The selected mod version filter for addons.
     */
    #[Url(as: 'versionFilter')]
    public ?int $selectedModVersionId = null;

    /**
     * Mount the component.
     */
    public function mount(int $modId, ?int $selectedModVersionId = null): void
    {
        $this->modId = $modId;

        // If URL parameter is set, use it (via #[Url] attribute)
        // Otherwise check for initial value passed from parent
        if ($this->selectedModVersionId === null && $selectedModVersionId !== null) {
            $this->selectedModVersionId = $selectedModVersionId;
        }
    }

    /**
     * Reset pagination when mod version filter changes.
     */
    public function updatedSelectedModVersionId(): void
    {
        $this->resetPage('addonPage');
    }

    /**
     * Render a placeholder while lazy loading.
     */
    public function placeholder(): View
    {
        return view('livewire.mod.show.addons-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $mod = Mod::query()->findOrFail($this->modId);
        $user = auth()->user();

        $addonCount = $mod->addons()
            ->when(! $user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->count();

        return view('livewire.mod.show.addons-tab', [
            'mod' => $mod,
            'addons' => $this->addons($mod),
            'addonCount' => $addonCount,
            'modVersionsForFilter' => $this->getModVersionsForFilter($mod),
        ]);
    }

    /**
     * Get mod versions for the filter dropdown.
     *
     * Limited to versions from the last two minor releases to reduce query size.
     *
     * @return Collection<int, ModVersion>
     */
    protected function getModVersionsForFilter(Mod $mod): Collection
    {
        // Get the last 2 distinct minor versions (major.minor combinations)
        $recentMinorVersions = $mod->versions()
            ->select(['version_major', 'version_minor'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->reorder() // Clear default ordering to avoid GROUP BY conflicts
            ->groupBy(['version_major', 'version_minor'])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->limit(2)
            ->get();

        if ($recentMinorVersions->isEmpty()) {
            return collect();
        }

        // Get all versions from those minor releases
        return $mod->versions()
            ->select(['id', 'mod_id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels'])
            ->with(['latestSptVersion:spt_versions.id,spt_versions.version'])
            ->publiclyVisible()
            ->where(function (Builder $query) use ($recentMinorVersions): void {
                foreach ($recentMinorVersions as $minorVersion) {
                    $query->orWhere(function (Builder $q) use ($minorVersion): void {
                        $q->where('version_major', $minorVersion->version_major)
                            ->where('version_minor', $minorVersion->version_minor);
                    });
                }
            })
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get();
    }

    /**
     * The mod's addons.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    protected function addons(Mod $mod): LengthAwarePaginator
    {
        $user = auth()->user();

        return $mod->addons()
            ->with([
                'owner',
                'additionalAuthors',
                'latestVersion',
                'mod.latestVersion:id,mod_id,version,version_major,version_minor,version_patch',
                'latestVersion.compatibleModVersions' => fn (Relation $query): mixed => $query
                    ->select(['mod_versions.id', 'mod_versions.mod_id', 'mod_versions.version', 'mod_versions.version_major', 'mod_versions.version_minor', 'mod_versions.version_patch'])
                    ->where('mod_id', $mod->id)
                    ->orderBy('version_major', 'desc')
                    ->orderBy('version_minor', 'desc')
                    ->orderBy('version_patch', 'desc'),
            ])
            ->when($this->selectedModVersionId, function (Builder $query): void {
                // Filter addons that have ANY version compatible with the selected mod version
                $query->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)
                        ->whereNotNull('published_at')
                        ->whereHas('compatibleModVersions', function (Builder $compatQuery): void {
                            $compatQuery->where('mod_versions.id', $this->selectedModVersionId);
                        });
                });
            })
            ->when(! $user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->orderBy('downloads', 'desc')
            ->paginate(perPage: 10, pageName: 'addonPage')
            ->fragment('addons');
    }
}
