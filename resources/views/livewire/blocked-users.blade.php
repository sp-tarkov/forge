<x-action-section>
    <x-slot name="title">
        {{ __('Blocked Users') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage users you have blocked. Blocked users cannot interact with you through comments, messages, or view your profile.') }}
    </x-slot>

    <x-slot name="content">
        @if ($blockedUsers->isEmpty())
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('You haven\'t blocked any users.') }}
            </div>
        @else
            <div class="space-y-2">
                @foreach ($blockedUsers as $block)
                    <div
                        class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center space-x-4">
                            <img
                                class="h-12 w-12 rounded-full"
                                src="{{ $block->blocked->profile_photo_url }}"
                                alt="{{ $block->blocked->name }}"
                            >
                            <div>
                                <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                                    {{ $block->blocked->name }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Blocked') }} {{ $block->created_at->diffForHumans() }}
                                </div>
                                @if ($block->reason)
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ __('Reason:') }} {{ $block->reason }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
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

            @if ($blockedUsers->hasPages())
                <div class="mt-4">
                    {{ $blockedUsers->links() }}
                </div>
            @endif
        @endif
    </x-slot>
</x-action-section>
