<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class FollowCards extends Component
{
    /**
     * The user account that is being viewed.
     */
    #[Locked]
    public User $profileUser;

    /**
     * A collection of user IDs that the auth user follows.
     *
     * @var Collection<int, int>
     */
    public Collection $authFollowIds;

    /**
     * Render the component.
     */
    public function render(): View
    {
        $this->updateAuthFollowIds();

        return view('livewire.user.follow-cards');
    }

    /**
     * Called when the user follows or unfollows a user.
     */
    #[On('user-follow-change')]
    public function updateAuthFollowIds(): void
    {
        // Fetch IDs of all users the authenticated user is following.
        $this->authFollowIds = collect();
        $authUser = auth()->user();
        if ($authUser) {
            $this->authFollowIds = $authUser->following()->pluck('following_id');
        }

        $this->dispatch('auth-follow-change');
    }
}
