<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
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
     * Get the mod.
     */
    #[Computed]
    public function mod(): Mod
    {
        return Mod::query()->findOrFail($this->modId);
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<int, ModVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        $user = auth()->user();

        return $this->mod // @phpstan-ignore return.type (Livewire computed property caching)
            ->versions()
            ->with(['latestSptVersion', 'sptVersions', 'latestDependenciesResolved.mod:id,name,slug'])
            ->unless($user?->can('viewAny', [ModVersion::class, $this->mod]), function (Builder $query): void {
                // Include both modern versions (with SPT tags) and legacy versions (empty constraint)
                $query->where(function (Builder $q): void {
                    $q->publiclyVisible()->orWhere(function (Builder $legacy): void {
                        $legacy->legacyPubliclyVisible();
                    });
                });
            })
            ->withCount([
                'compatibleAddonVersions as compatible_addons_count' => function (Builder $query) use ($user): void {
                    // Only count published, enabled addons for non-privileged users
                    $query->whereHas('addon', function (Builder $addonQuery) use ($user): void {
                        $addonQuery->whereNull('detached_at');

                        if (!$user?->isModOrAdmin()) {
                            $addonQuery->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
                        }
                    });
                },
            ])
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }
};
