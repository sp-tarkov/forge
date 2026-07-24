<?php

declare(strict_types=1);

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
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
     * The size of the button.
     */
    public string $size = 'sm';

    /**
     * Whether a block relationship prevents following the profile user.
     */
    #[Computed]
    public function isBlockedFromFollowing(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasBlocked($this->profileUserId)) {
            return true;
        }
        return (bool) $user->isBlockedBy($this->profileUserId);
    }

    /**
     * Action to follow a user.
     */
    public function follow(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if ($this->isBlockedFromFollowing) {
            Flux::toast(heading: 'Error', text: 'You cannot follow this user.', variant: 'danger');

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
