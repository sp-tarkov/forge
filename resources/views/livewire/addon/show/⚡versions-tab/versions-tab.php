<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    use ModeratesAddon;
    use WithPagination;

    /**
     * The addon ID.
     */
    public int $addonId;

    /**
     * Mount the component.
     */
    public function mount(int $addonId): void
    {
        $this->addonId = $addonId;
    }

    /**
     * Get the addon.
     */
    #[Computed]
    public function addon(): Addon
    {
        return Addon::query()->findOrFail($this->addonId);
    }

    /**
     * The addon's versions.
     *
     * @return LengthAwarePaginator<int, AddonVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        return $this->addon // @phpstan-ignore return.type (Livewire computed property caching)
            ->versions()
            ->with(['compatibleModVersions'])
            ->unless(
                auth()
                    ->user()
                    ?->can('viewAny', [AddonVersion::class, $this->addon]),
                function (Builder $query): void {
                    $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
                },
            )
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }
};
