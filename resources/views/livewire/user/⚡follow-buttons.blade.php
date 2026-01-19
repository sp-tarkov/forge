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
};
?>

@props(['isFollowing'])

<form>
    @if ($isFollowing)
        {{-- Following button --}}
        <flux:button
            wire:click="unfollow"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.heart
                    variant="solid"
                    class="text-red-500 {{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5"
                />
                {{ __('Following') }}
            </div>
        </flux:button>
    @else
        {{-- Follow button --}}
        <flux:button
            wire:click="follow"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.heart
                    variant="outline"
                    class="text-white {{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5"
                />
                {{ __('Follow') }}
            </div>
        </flux:button>
    @endif
</form>
