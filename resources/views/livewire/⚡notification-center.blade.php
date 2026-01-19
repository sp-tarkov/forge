<?php

declare(strict_types=1);

use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * Current count of unread notifications for the authenticated user. Updated when notifications are read or deleted.
     */
    public int $unreadCount = 0;

    /**
     * Initialize the component when it's first mounted.
     */
    public function mount(): void
    {
        // Load the initial unread notification count.
        $this->loadUnreadCount();
    }

    /**
     * Called when the component is hydrated from a later request.
     */
    public function hydrate(): void
    {
        // Ensures the unread count is always current.
        $this->loadUnreadCount();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();

            // Update the unread notification count.
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
     * Updates the unread notification count.
     */
    private function loadUnreadCount(): void
    {
        $this->unreadCount = Auth::user()->unreadNotifications()->count();
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
     * Fetch paginated notifications for the authenticated user.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    private function fetchNotifications(): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, DatabaseNotification> */
        return Auth::user()->notifications()->orderBy('created_at', 'desc')->paginate(8, pageName: 'notificationPage');
    }
};
?>

<div
    wire:poll.30s="$refresh"
    id="notifications"
    class="p-6"
>
    @php
        $notifications = $this->fetchNotifications();
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ __('Notifications') }}
        </h3>
        <div class="flex items-center gap-3">
            @if ($notifications->count() > 0)
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        wire:loading.attr="disabled"
                        class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 disabled:opacity-50 transition-colors"
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
                    class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-50 transition-colors"
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
    @if ($notifications->isEmpty())
        <div class="text-center py-12">
            <flux:icon.bell-slash class="mx-auto size-12 text-gray-300 dark:text-gray-700" />
            <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                {{ __('No notifications') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __("You're all caught up! New notifications will appear here.") }}
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($notifications as $notification)
                <div
                    wire:key="notification-{{ $notification->id }}"
                    class="relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden"
                >
                    {{-- Unread indicator bar --}}
                    @if (!$notification->read_at)
                        <div class="absolute inset-y-0 left-0 w-1 bg-blue-500"></div>
                    @endif

                    <div
                        wire:click="reviewNotification('{{ $notification->id }}')"
                        class="w-full text-left p-4 {{ !$notification->read_at ? 'pl-5' : '' }} cursor-pointer"
                    >
                        <div class="flex items-center gap-4">
                            {{-- Icon (vertically centered) --}}
                            <div class="flex-shrink-0">
                                @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                    <div
                                        class="size-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                                        <flux:icon.exclamation-triangle class="size-5 text-red-600 dark:text-red-400" />
                                    </div>
                                @elseif ($notification->type === 'App\Notifications\NewChatMessageNotification')
                                    <div
                                        class="size-10 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center">
                                        <flux:icon.chat-bubble-left-right
                                            class="size-5 text-purple-600 dark:text-purple-400"
                                        />
                                    </div>
                                @else
                                    <div
                                        class="size-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                        <flux:icon.chat-bubble-left-ellipsis
                                            class="size-5 text-blue-600 dark:text-blue-400"
                                        />
                                    </div>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $notification->data['reporter_name'] ?? __('Someone') }}
                                                <span
                                                    class="font-normal text-gray-600 dark:text-gray-400">{{ __('reported') }}</span>
                                                <span
                                                    class="text-red-600 dark:text-red-400">{{ Str::lower($notification->data['reason_label'] ?? __('content')) }}</span>
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $notification->data['reportable_title'] ?? __('Content') }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            @if (!$notification->read_at)
                                                <button
                                                    type="button"
                                                    wire:click.stop="markAsRead('{{ $notification->id }}')"
                                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                                >
                                                    {{ __('Mark read') }}
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click.stop="deleteNotification('{{ $notification->id }}')"
                                                wire:confirm="{{ __('Are you sure you want to delete this notification?') }}"
                                                class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                            <span
                                                class="text-xs text-gray-400 dark:text-gray-500"
                                                title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                            >
                                                {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($notification->data['context'] ?? null)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 italic line-clamp-2">
                                            "{{ Str::limit($notification->data['context'], 150) }}"
                                        </p>
                                    @endif
                                @elseif ($notification->type === 'App\Notifications\NewChatMessageNotification')
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $notification->data['sender_name'] ?? __('Someone') }}
                                                <span class="font-normal text-gray-600 dark:text-gray-400">
                                                    @if (($notification->data['message_count'] ?? 1) > 1)
                                                        {{ __('sent you') }}
                                                        <span class="text-purple-600 dark:text-purple-400">
                                                            {{ __(':count new messages', ['count' => $notification->data['message_count']]) }}
                                                        </span>
                                                    @else
                                                        {{ __('sent you a') }}
                                                        <span
                                                            class="text-purple-600 dark:text-purple-400">{{ __('new message') }}</span>
                                                    @endif
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            @if (!$notification->read_at)
                                                <button
                                                    type="button"
                                                    wire:click.stop="markAsRead('{{ $notification->id }}')"
                                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                                >
                                                    {{ __('Mark read') }}
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click.stop="deleteNotification('{{ $notification->id }}')"
                                                wire:confirm="{{ __('Are you sure you want to delete this notification?') }}"
                                                class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                            <span
                                                class="text-xs text-gray-400 dark:text-gray-500"
                                                title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                            >
                                                {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($notification->data['latest_message_preview'] ?? null)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 italic line-clamp-2">
                                            "{{ Str::limit($notification->data['latest_message_preview'], 150) }}"
                                        </p>
                                    @endif
                                @else
                                    {{-- Comment notification --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $notification->data['commenter_name'] ?? __('Someone') }}
                                                <span
                                                    class="font-normal text-gray-600 dark:text-gray-400">{{ __('commented on') }}</span>
                                                <span
                                                    class="text-blue-600 dark:text-blue-400">{{ Str::limit($notification->data['commentable_title'] ?? __('your content'), 40) }}</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            @if (!$notification->read_at)
                                                <button
                                                    type="button"
                                                    wire:click.stop="markAsRead('{{ $notification->id }}')"
                                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                                >
                                                    {{ __('Mark read') }}
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click.stop="deleteNotification('{{ $notification->id }}')"
                                                wire:confirm="{{ __('Are you sure you want to delete this notification?') }}"
                                                class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                            <span
                                                class="text-xs text-gray-400 dark:text-gray-500"
                                                title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                            >
                                                {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($notification->data['comment_body'] ?? null)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 italic line-clamp-2">
                                            "{{ Str::limit($notification->data['comment_body'], 150) }}"
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($notifications->hasPages())
            <div class="mt-6">
                {{ $notifications->links(data: ['scrollTo' => '#notifications']) }}
            </div>
        @endif
    @endif
</div>
