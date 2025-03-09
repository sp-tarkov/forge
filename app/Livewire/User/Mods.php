<?php

namespace App\Livewire\User;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Mods extends Component
{
    /**
     * The user to display mods for.
     */
    public User $user;

    /**
     * The mods for the user.
     *
     * @return LengthAwarePaginator<Mod>
     */
    #[Computed(persist: true, seconds: 600)]
    public function mods(): LengthAwarePaginator
    {
        return $this->user->mods()
            ->unless(request()->user()?->can('viewDisabled', $this->user), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereHas('latestVersion');
            })
            ->with([
                'users',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->fragment('mods');
    }

    /**
     * Refresh the mod listing.
     */
    #[On('mod-delete')]
    public function refreshListing(): void
    {
        unset($this->mods);
        $this->render();
    }

    /**
     * Render the component.
     */
    public function render(): View|string
    {
        return view('livewire.user.mods');
    }
}
