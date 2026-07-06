<div
    wire:poll.30s="$refresh"
    x-data="{ notificationDropdownOpen: false }"
    x-on:keydown.esc.window="notificationDropdownOpen = false"
    class="relative"
>
    <flux:button
        variant="subtle"
        square="true"
        class="group relative"
        x-on:click="notificationDropdownOpen = !notificationDropdownOpen"
        x-bind:aria-expanded="notificationDropdownOpen"
        aria-haspopup="true"
        aria-label="{{ __('Notifications') }}"
    >
        <flux:icon.bell
            variant="mini"
            class="text-white"
        />
        @if ($unreadCount > 0)
            <span
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
            >
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </flux:button>

    <div
        x-cloak
        x-show="notificationDropdownOpen"
        x-transition
        x-on:click.outside="notificationDropdownOpen = false"
        class="absolute right-0 top-11 z-[100] flex w-full min-w-[22rem] flex-col overflow-hidden rounded-xl border border-gray-700 bg-gray-800"
        role="menu"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-700 px-4 py-3">
            <h3 class="text-sm font-semibold text-white">
                {{ __('Unread Notifications') }}
            </h3>
            <div class="flex items-center gap-2">
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        wire:loading.attr="disabled"
                        class="text-xs text-blue-400 hover:text-blue-300 disabled:opacity-50"
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
                @if ($this->notifications->count() > 0)
                    <button
                        type="button"
                        wire:click="deleteAll"
                        wire:loading.attr="disabled"
                        wire:confirm="{{ __('Are you sure you want to delete all notifications?') }}"
                        class="text-xs text-red-400 hover:text-red-300 disabled:opacity-50"
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
            </div>
        </div>

        {{-- Notification List --}}
        @if ($this->notifications->count() > 0)
            <div class="flex max-h-96 flex-col divide-y divide-gray-700 overflow-y-auto">
                @foreach ($this->notifications as $notification)
                    <x-notification-row.nav
                        :notification="$notification"
                        :presentation="$this->presentationFor($notification)"
                    />
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="flex flex-col border-t border-gray-700 py-1.5">
                <a
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    x-on:click="notificationDropdownOpen = false"
                    class="focus-visible:outline-hidden flex items-center gap-2 bg-gray-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
                    role="menuitem"
                >
                    <flux:icon.arrow-right class="h-4 w-4" />
                    {{ __('View all notifications') }}
                </a>
            </div>
        @else
            {{-- Empty State --}}
            <div class="px-4 py-8 text-center">
                <flux:icon.bell class="mx-auto mb-3 h-12 w-12 text-gray-600" />
                <p class="text-sm text-gray-400">
                    {{ __('No new notifications') }}
                </p>
            </div>
        @endif
    </div>
</div>
