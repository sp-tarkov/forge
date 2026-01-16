<?php

declare(strict_types=1);

use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    /**
     * Current count of unread notifications for the authenticated user.
     */
    public int $unreadCount = 0;

    /**
     * Initialize the component when it's first mounted.
     */
    public function mount(): void
    {
        $this->loadUnreadCount();
    }

    /**
     * Called when the component is hydrated from a later request.
     */
    public function hydrate(): void
    {
        $this->loadUnreadCount();
    }

    /**
     * Review a notification - marks it as read and redirects to its review URL.
     */
    public function reviewNotification(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if (!$notification) {
            return;
        }

        // Mark as read if not already
        if (!$notification->read_at) {
            $notification->markAsRead();
            $this->loadUnreadCount();
        }

        // Get the appropriate review URL based on notification type
        $url = $this->getReviewUrl($notification);

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
            $this->loadUnreadCount();
        }
    }

    /**
     * Mark all unread notifications as read for the current user.
     */
    public function markAllAsRead(): void
    {
        Auth::user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);
        $this->loadUnreadCount();
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification) {
            $wasUnread = !$notification->read_at;
            $notification->delete();

            if ($wasUnread) {
                $this->loadUnreadCount();
            }
        }
    }

    /**
     * Delete all notifications for the current user.
     */
    public function deleteAll(): void
    {
        Auth::user()->notifications()->delete();
        $this->loadUnreadCount();
    }

    /**
     * Get the review URL for a notification based on its type.
     */
    private function getReviewUrl(DatabaseNotification $notification): ?string
    {
        return match ($notification->type) {
            ReportSubmittedNotification::class => $notification->data['reportable_url'] ?? null,
            NewChatMessageNotification::class => $notification->data['conversation_url'] ?? null,
            NewCommentNotification::class => $notification->data['comment_url'] ?? null,
            default => null,
        };
    }

    /**
     * Updates the unread notification count.
     */
    private function loadUnreadCount(): void
    {
        $this->unreadCount = Auth::user()->unreadNotifications()->count();
    }

    /**
     * Fetch recent unread notifications for the authenticated user.
     *
     * @return Collection<int, DatabaseNotification>
     */
    private function fetchNotifications(): Collection
    {
        /** @var Collection<int, DatabaseNotification> */
        return Auth::user()->unreadNotifications()->orderBy('created_at', 'desc')->limit(10)->get();
    }
};
?>

<div
    wire:poll.30s="$refresh"
    x-data="{ notificationDropdownOpen: false }"
    x-on:keydown.esc.window="notificationDropdownOpen = false"
    class="relative"
>
    <button
        type="button"
        x-on:click="notificationDropdownOpen = !notificationDropdownOpen"
        class="relative rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out"
        :aria-expanded="notificationDropdownOpen"
        aria-haspopup="true"
    >
        <flux:icon.bell class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span
                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
            >
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
        <span class="sr-only">{{ __('Notifications') }}</span>
    </button>

    @php
        $notifications = $this->fetchNotifications();
    @endphp

    <div
        x-cloak
        x-show="notificationDropdownOpen"
        x-transition
        x-on:click.outside="notificationDropdownOpen = false"
        class="absolute top-11 right-0 z-[100] flex w-full min-w-[22rem] flex-col overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
        role="menu"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-300 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ __('Unread Notifications') }}
            </h3>
            <div class="flex items-center gap-2">
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        wire:loading.attr="disabled"
                        class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 disabled:opacity-50"
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
                @if ($notifications->count() > 0)
                    <button
                        type="button"
                        wire:click="deleteAll"
                        wire:loading.attr="disabled"
                        wire:confirm="{{ __('Are you sure you want to delete all notifications?') }}"
                        class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-50"
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
        @if ($notifications->count() > 0)
            <div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                @foreach ($notifications as $notification)
                    <button
                        type="button"
                        wire:key="nav-notification-{{ $notification->id }}"
                        wire:click="reviewNotification('{{ $notification->id }}')"
                        x-on:click="notificationDropdownOpen = false"
                        class="flex items-start gap-3 px-4 py-3 text-left hover:bg-gray-200/50 dark:hover:bg-gray-700/50 transition-colors"
                    >
                        {{-- Notification Type Icon --}}
                        <div class="flex-shrink-0 mt-0.5">
                            @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                    <flux:icon.exclamation-triangle class="w-4 h-4 text-white" />
                                </div>
                            @elseif ($notification->type === 'App\Notifications\NewChatMessageNotification')
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <flux:icon.chat-bubble-left-right class="w-4 h-4 text-white" />
                                </div>
                            @else
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <flux:icon.chat-bubble-left-ellipsis class="w-4 h-4 text-white" />
                                </div>
                            @endif
                        </div>

                        {{-- Notification Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                        {{ $notification->data['reporter_name'] ?? __('Someone') }}
                                    @elseif ($notification->type === 'App\Notifications\NewChatMessageNotification')
                                        {{ $notification->data['sender_name'] ?? __('Someone') }}
                                    @else
                                        {{ $notification->data['commenter_name'] ?? __('Someone') }}
                                    @endif
                                </span>
                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0"
                                    title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                >
                                    {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans(short: true) }}
                                </span>
                            </div>

                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 truncate">
                                @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                    {{ __('reported') }}
                                    {{ Str::limit($notification->data['reportable_title'] ?? __('content'), 30) }}
                                @elseif ($notification->type === 'App\Notifications\NewChatMessageNotification')
                                    @if (($notification->data['message_count'] ?? 1) > 1)
                                        {{ __('sent you') }} {{ $notification->data['message_count'] }}
                                        {{ __('messages') }}
                                    @else
                                        {{ __('sent you a message') }}
                                    @endif
                                @else
                                    {{ __('commented on') }}
                                    {{ Str::limit($notification->data['commentable_title'] ?? __('your content'), 30) }}
                                @endif
                            </p>

                            {{-- Preview text --}}
                            @if ($notification->type === 'App\Notifications\ReportSubmittedNotification' && ($notification->data['context'] ?? null))
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1 italic truncate">
                                    "{{ Str::limit($notification->data['context'], 50) }}"
                                </p>
                            @elseif (
                                $notification->type === 'App\Notifications\NewChatMessageNotification' &&
                                    ($notification->data['latest_message_preview'] ?? null))
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1 italic truncate">
                                    "{{ Str::limit($notification->data['latest_message_preview'], 50) }}"
                                </p>
                            @elseif ($notification->type === 'App\Notifications\NewCommentNotification' && ($notification->data['comment_body'] ?? null))
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1 italic truncate">
                                    "{{ Str::limit($notification->data['comment_body'], 50) }}"
                                </p>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="flex flex-col py-1.5 border-t border-slate-300 dark:border-gray-700">
                <a
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    x-on:click="notificationDropdownOpen = false"
                    class="flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-gray-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                    role="menuitem"
                >
                    <flux:icon.arrow-right class="w-4 h-4" />
                    {{ __('View all notifications') }}
                </a>
            </div>
        @else
            {{-- Empty State --}}
            <div class="px-4 py-8 text-center">
                <flux:icon.bell class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" />
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('No new notifications') }}
                </p>
            </div>
        @endif
    </div>
</div>
