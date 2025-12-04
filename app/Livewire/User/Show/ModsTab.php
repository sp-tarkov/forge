<?php

declare(strict_types=1);

namespace App\Livewire\User\Show;

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class ModsTab extends Component
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
     * Render the placeholder while loading.
     */
    public function placeholder(): View
    {
        return view('livewire.user.show.mods-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $user = User::query()->findOrFail($this->userId);

        return view('livewire.user.show.mods-tab', [
            'user' => $user,
            'mods' => $this->getUserMods($user),
        ]);
    }

    /**
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<int, Mod>
     */
    protected function getUserMods(User $user): LengthAwarePaginator
    {
        $viewer = Auth::user();

        $query = $user->ownedAndAuthoredMods()
            ->with([
                'owner:id,name',
                'additionalAuthors:id,name',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->latest();

        if (! $viewer?->can('viewDisabledUserMods', $user)) {
            $query->whereDisabled(false)
                ->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)->whereNotNull('published_at');
                });
        }

        return $query->paginate(10)
            ->fragment('mods');
    }
}
