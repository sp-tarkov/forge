<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
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
     * Mount the component.
     */
    public function mount(): void
    {
        $this->updateAuthFollowIds();
    }

    /**
     * Called when the user follows or unfollows a user.
     */
    #[On('user-follow-change')]
    public function updateAuthFollowIds(): void
    {
        // Fetch IDs of all users the authenticated user is following.
        $authUser = auth()->user();
        if ($authUser) {
            /** @var Collection<int, int> $followIds */
            $followIds = $authUser->following()->pluck('following_id');
            $this->authFollowIds = $followIds;
        } else {
            /** @var Collection<int, int> $emptyIds */
            $emptyIds = collect();
            $this->authFollowIds = $emptyIds;
        }

        $this->dispatch('auth-follow-change');
    }
};
