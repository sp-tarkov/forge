<?php

declare(strict_types=1);

namespace App\Livewire\Mod\Show;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class VersionsTab extends Component
{
    use ModeratesModVersion;
    use WithPagination;

    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->modId = $modId;
    }

    /**
     * Render a placeholder while lazy loading.
     */
    public function placeholder(): View
    {
        return view('livewire.mod.show.versions-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $mod = Mod::query()->findOrFail($this->modId);

        return view('livewire.mod.show.versions-tab', [
            'mod' => $mod,
            'versions' => $this->versions($mod),
        ]);
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<int, ModVersion>
     */
    protected function versions(Mod $mod): LengthAwarePaginator
    {
        $user = auth()->user();

        return $mod->versions()
            ->with([
                'latestSptVersion',
                'sptVersions',
                'latestResolvedDependencies.mod:id,name,slug',
            ])
            ->unless($user?->can('viewAny', [ModVersion::class, $mod]), function (Builder $query): void {
                $query->publiclyVisible();
            })
            ->withCount([
                'compatibleAddonVersions as compatible_addons_count' => function (Builder $query) use ($user): void {
                    // Only count published, enabled addons for non-privileged users
                    $query->whereHas('addon', function (Builder $addonQuery) use ($user): void {
                        $addonQuery->whereNull('detached_at');

                        if (! $user?->isModOrAdmin()) {
                            $addonQuery->where('disabled', false)
                                ->whereNotNull('published_at')
                                ->where('published_at', '<=', now());
                        }
                    });
                },
            ])
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }
}
