<x-action-section>
    <x-slot name="title">
        {{ __('Blocked Users') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage users you have blocked. Blocked users cannot interact with you through comments, messages, or view your profile.') }}
    </x-slot>

    <x-slot name="content">
        @if ($this->blockedUsers->isEmpty())
            <flux:text>{{ __('You haven\'t blocked any users.') }}</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($this->blockedUsers as $block)
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-white/10 bg-white/5 p-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <img
                                class="size-12 shrink-0 rounded-full object-cover"
                                src="{{ $block->blocked->profile_photo_url }}"
                                alt="{{ $block->blocked->name }}"
                            >
                            <div class="min-w-0">
                                <flux:heading size="sm" class="truncate">
                                    {{ $block->blocked->name }}
                                </flux:heading>
                                <flux:text size="sm">
                                    {{ __('Blocked') }} {{ $block->created_at->diffForHumans() }}
                                </flux:text>
                                @if ($block->reason)
                                    <flux:text size="sm" class="mt-1">
                                        {{ __('Reason:') }} {{ $block->reason }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0">
                            <flux:button
                                wire:click="unblockUser({{ $block->blocked->id }})"
                                variant="outline"
                                size="sm"
                                wire:loading.attr="disabled"
                                class="whitespace-nowrap"
                            >
                                <div class="flex items-center">
                                    <flux:icon.x-mark
                                        variant="micro"
                                        class="mr-1.5"
                                        wire:loading.remove
                                    />
                                    <flux:icon.arrow-path
                                        variant="micro"
                                        class="mr-1.5 animate-spin"
                                        wire:loading
                                    />
                                    {{ __('Unblock') }}
                                </div>
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->blockedUsers->hasPages())
                <div class="mt-4">
                    {{ $this->blockedUsers->links() }}
                </div>
            @endif
        @endif
    </x-slot>
</x-action-section>
