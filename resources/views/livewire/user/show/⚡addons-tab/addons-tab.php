<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\User;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component
{
    use ModeratesAddon;
    use WithPagination;

    /**
     * The user ID whose addons are being shown.
     */
    public int $userId;

    /**
     * Mount the component.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the user.
     */
    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    /**
     * Get the addons for the user.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    #[Computed]
    public function addons(): LengthAwarePaginator
    {
        $query = $this->user->visibleAddonsFor(auth()->user())
            ->with(['owner', 'additionalAuthors', 'latestVersion', 'mod:id,name,slug', 'mod.latestVersion:id,mod_id,version_major,version_minor,version_patch', 'versions.compatibleModVersions'])
            ->orderBy('addons.downloads', 'desc');

        // Total with addons.id so the LEFT JOIN against additional_authors doesn't inflate the paginator total.
        $total = $query->toBase()->getCountForPagination(['addons.id']);

        $paginator = $query->paginate(perPage: 10, pageName: 'addonPage', total: $total);
        $paginator->fragment('addons');

        return $paginator;
    }
};
