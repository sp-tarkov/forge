<?php

declare(strict_types=1);

namespace App\Livewire\User;

use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FollowButtons extends Component
{
    /**
     * The ID of the user whose profile is being viewed.
     */
    #[Locked]
    public int $profileUserId;

    /**
     * Whether the authenticated user is currently following the profile user.
     */
    public bool $isFollowing;

    /**
     * Action to follow a user.
     */
    public function follow(): void
    {
        auth()->user()->follow($this->profileUserId);
        $this->isFollowing = true;

        $this->dispatch('user-follow-change');
    }

    /**
     * Action to unfollow a user.
     */
    public function unfollow(): void
    {
        auth()->user()->unfollow($this->profileUserId);
        $this->isFollowing = false;

        $this->dispatch('user-follow-change');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.user.follow-buttons');
    }
}
