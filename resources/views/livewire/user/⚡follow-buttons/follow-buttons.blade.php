@props(['isFollowing'])

<form>
    @if ($this->isBlockedFromFollowing)
        {{-- No buttons are shown when a block relationship exists --}}
    @elseif ($isFollowing)
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
                    class="{{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5 text-red-500"
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
                    class="{{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5 text-white"
                />
                {{ __('Follow') }}
            </div>
        </flux:button>
    @endif
</form>
