<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * NavigationNotifications Livewire Component
 *
 * Displays a notification bell icon in the navigation with an unread count badge.
 * Opens a flyout dropdown showing recent unread notifications with actions to
 * mark as read, delete, and navigate to the notification's review URL.
 */
class NavigationNotifications extends Component
{
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

        if (! $notification) {
            return;
        }

        // Mark as read if not already
        if (! $notification->read_at) {
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

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
            $this->loadUnreadCount();
        }
    }

    /**
     * Mark all unread notifications as read for the current user.
     */
    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->loadUnreadCount();
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification) {
            $wasUnread = ! $notification->read_at;
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
     * Render the navigation notifications component.
     */
    public function render(): View
    {
        return view('livewire.navigation-notifications', [
            'notifications' => $this->fetchNotifications(),
        ]);
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
        return Auth::user()
            ->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
}
