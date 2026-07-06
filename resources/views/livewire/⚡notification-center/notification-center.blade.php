<div
    wire:poll.30s="$refresh"
    id="notifications"
    class="p-6"
>
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-white">
            {{ __('Notifications') }}
        </h3>
        <div class="flex items-center gap-3">
            @if ($this->notifications->count() > 0)
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        wire:loading.attr="disabled"
                        class="text-xs text-blue-400 transition-colors hover:text-blue-300 disabled:opacity-50"
                    >
                        <span
                            wire:loading.remove
                            wire:target="markAllAsRead"
                        >{{ __('Mark all read') }}</span>
                        <span
                            wire:loading
                            wire:target="markAllAsRead"
                        >{{ __('Marking...') }}</span>
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="deleteAll"
                    wire:loading.attr="disabled"
                    wire:confirm="{{ __('Are you sure you want to delete all notifications?') }}"
                    class="text-xs text-red-400 transition-colors hover:text-red-300 disabled:opacity-50"
                >
                    <span
                        wire:loading.remove
                        wire:target="deleteAll"
                    >{{ __('Delete all') }}</span>
                    <span
                        wire:loading
                        wire:target="deleteAll"
                    >{{ __('Deleting...') }}</span>
                </button>
            @endif
            @if ($unreadCount > 0)
                <flux:badge
                    color="red"
                    size="sm"
                >
                    {{ $unreadCount }} {{ __('Unread') }}
                </flux:badge>
            @else
                <flux:badge
                    color="gray"
                    size="sm"
                >
                    {{ __('All Read') }}
                </flux:badge>
            @endif
        </div>
    </div>

    {{-- Notifications List --}}
    @if ($this->notifications->isEmpty())
        <div class="py-12 text-center">
            <flux:icon.bell-slash class="mx-auto size-12 text-gray-700" />
            <h3 class="mt-4 text-sm font-medium text-white">
                {{ __('No notifications') }}
            </h3>
            <p class="mt-1 text-sm text-gray-400">
                {{ __("You're all caught up! New notifications will appear here.") }}
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->notifications as $notification)
                <x-notification-row.dashboard
                    :notification="$notification"
                    :presentation="$this->presentationFor($notification)"
                />
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($this->notifications->hasPages())
            <div class="mt-6">
                {{ $this->notifications->links(data: ['scrollTo' => '#notifications']) }}
            </div>
        @endif
    @endif
</div>
