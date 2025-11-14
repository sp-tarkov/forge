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
