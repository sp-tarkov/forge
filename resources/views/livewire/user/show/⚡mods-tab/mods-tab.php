<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
        $user = $this->user;
        $viewer = Auth::user();

        $query = $user
            ->ownedAndAuthoredMods()
            ->with(['owner:id,name', 'additionalAuthors:id,name', 'latestVersion', 'latestVersion.latestSptVersion'])
            ->latest();

        if (! $viewer?->can('viewDisabledUserMods', $user)) {
            $query->whereDisabled(false)->whereHas('versions', function (Builder $versionQuery): void {
                $versionQuery->where('disabled', false)->whereNotNull('published_at');
            });
        }

        return $query->paginate(10)->fragment('mods'); // @phpstan-ignore return.type (Livewire computed property caching)
    }
};
