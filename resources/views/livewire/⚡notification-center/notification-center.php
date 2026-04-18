<?php

declare(strict_types=1);

use App\Notifications\CommentReplyNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
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
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            $this->clearUnreadCountCache();
            $this->loadUnreadCount();
        }
    }

    /**
     * Mark all unread notifications as read for the current user.
     */
    public function markAllAsRead(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->update(['read_at' => now()]);
        $this->clearUnreadCountCache();
        $this->loadUnreadCount();
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(string $notificationId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);
        if ($notification) {
            $wasUnread = ! $notification->read_at;
            $notification->delete();
            if ($wasUnread) {
                $this->clearUnreadCountCache();
                $this->loadUnreadCount();
            }
        }
    }

    /**
     * Delete all notifications for the current user.
     */
    public function deleteAll(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->notifications()->delete();
        $this->clearUnreadCountCache();
        $this->loadUnreadCount();
    }

    /**
     * Review a notification - marks it as read and redirects to its review URL.
     */
    public function reviewNotification(string $notificationId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return;
        }

        // Mark as read if not already
        if (! $notification->read_at) {
            $notification->markAsRead();
            $this->clearUnreadCountCache();
            $this->loadUnreadCount();
        }

        // Get the appropriate review URL based on notification type
        $url = $this->getReviewUrl($notification);

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    /**
     * Fetch paginated notifications for the authenticated user.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    /** @phpstan-ignore missingType.generics */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        $user = Auth::user();
        if (! $user) {
            return new LengthAwarePaginator([], 0, 8, null, ['pageName' => 'notificationPage']);
        }

        /** @var LengthAwarePaginator<int, DatabaseNotification> */
        return $user->notifications()->orderBy('created_at', 'desc')->paginate(8, pageName: 'notificationPage');
    }

    /**
     * Updates the unread notification count using a short-lived cache.
     */
    private function loadUnreadCount(): void
    {
        $userId = Auth::id();

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->unreadCount = (int) Cache::remember(sprintf('user:%s:unread-notification-count', $userId), 30, fn (): int => $user->unreadNotifications()->count());
    }

    /**
     * Clears the cached unread notification count so the next load fetches fresh data.
     */
    private function clearUnreadCountCache(): void
    {
        Cache::forget('user:'.Auth::id().':unread-notification-count');
    }

    /**
     * Get the review URL for a notification based on its type.
     */
    private function getReviewUrl(DatabaseNotification $notification): ?string
    {
        /** @var array{reportable_url?: string, conversation_url?: string, comment_url?: string} $data */
        $data = $notification->data;

        return match ($notification->type) {
            ReportSubmittedNotification::class => $data['reportable_url'] ?? null,
            NewChatMessageNotification::class => $data['conversation_url'] ?? null,
            NewCommentNotification::class,
            CommentReplyNotification::class => $data['comment_url'] ?? null,
            default => null,
        };
    }
};
