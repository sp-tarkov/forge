<?php

declare(strict_types=1);

use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
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
     * The size of the button.
     */
    public string $size = 'sm';

    /**
     * Action to follow a user.
     */
    public function follow(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->follow($this->profileUserId);
        $this->isFollowing = true;

        $this->dispatch('user-follow-change');
    }

    /**
     * Action to unfollow a user.
     */
    public function unfollow(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->unfollow($this->profileUserId);
        $this->isFollowing = false;

        $this->dispatch('user-follow-change');
    }
};
