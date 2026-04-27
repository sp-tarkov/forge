<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component
{
    use ModeratesMod;
    use WithPagination;

    /**
     * The user ID whose mods are being shown.
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
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<int, Mod>
     */
    #[Computed]
    public function mods(): LengthAwarePaginator
    {
        $query = $this->user->visibleModsFor(auth()->user())
            ->with(['owner:id,name', 'additionalAuthors:id,name', 'latestVersion', 'latestVersion.latestSptVersion'])
            ->latest();

        // Total using mods.id so the LEFT JOIN against additional_authors doesn't inflate the paginator total.
        $total = $query->toBase()->getCountForPagination(['mods.id']);

        $paginator = $query->paginate(perPage: 10, total: $total);
        $paginator->fragment('mods');

        return $paginator;
    }
};
