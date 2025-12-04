<?php

declare(strict_types=1);

namespace App\Livewire\Addon\Show;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class VersionsTab extends Component
{
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
     * Render a placeholder while lazy loading.
     */
    public function placeholder(): View
    {
        return view('livewire.addon.show.versions-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $addon = Addon::query()->findOrFail($this->addonId);

        return view('livewire.addon.show.versions-tab', [
            'addon' => $addon,
            'versions' => $this->versions($addon),
        ]);
    }

    /**
     * The addon's versions.
     *
     * @return LengthAwarePaginator<int, AddonVersion>
     */
    protected function versions(Addon $addon): LengthAwarePaginator
    {
        return $addon->versions()
            ->with(['compatibleModVersions'])
            ->unless(auth()->user()?->can('viewAny', [AddonVersion::class, $addon]), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }
}
