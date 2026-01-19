<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
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
        $this->authFollowIds = collect();
        $authUser = auth()->user();
        if ($authUser) {
            $this->authFollowIds = $authUser->following()->pluck('following_id');
        }

        $this->dispatch('auth-follow-change');
    }
};
?>

@props(['profileUser', 'authFollowIds' => collect()])

<div class="grid grid-cols-2 w-full gap-6">
    <div class="col-span-full md:col-span-1 lg:col-span-2 flex w-full">
        <livewire:user.follow-card
            relationship="followers"
            :profile-user="$profileUser"
            :auth-follow-ids="$authFollowIds"
        />
    </div>
    <div class="col-span-full md:col-span-1 lg:col-span-2 flex w-full">
        <livewire:user.follow-card
            relationship="following"
            :profile-user="$profileUser"
            :auth-follow-ids="$authFollowIds"
        />
    </div>
</div>
