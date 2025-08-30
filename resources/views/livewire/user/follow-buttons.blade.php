@props(['isFollowing'])

<form>
    @if ($isFollowing)
        {{-- Following button --}}
        <flux:button wire:click="unfollow" variant="outline" size="sm" class="whitespace-nowrap">
            <div class="flex items-center">
                <flux:icon.heart variant="solid" class="text-red-500 mr-1.5" />
                {{ __('Following') }}
            </div>
        </flux:button>
    @else
        {{-- Follow button --}}
        <flux:button wire:click="follow" variant="outline" size="sm" class="whitespace-nowrap">
            <div class="flex items-center">
                <flux:icon.heart variant="outline" class="text-white mr-1.5" />
                {{ __('Follow') }}
            </div>
        </flux:button>
    @endif
</form>
