<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * NotificationCenter Livewire Component
 *
 * This component handles the display and management of user notifications, providing functionality to view, mark as
 * read, and delete notifications with real-time updates of the unread count.
 */
class NotificationCenter extends Component
{
    use WithPagination;

    /**
     * Current count of unread notifications for the authenticated user. Updated when notifications are read or deleted.
     */
    public int $unreadCount = 0;

    /**
     * Custom pagination view for the notification list.
     */
    public function paginationView(): string
    {
        return 'livewire.pagination.tailwind-narrow';
    }

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
     * Render the notification center component.
     *
     * Retrieve paginated notifications, ordered by creation date, and passes them to the view.
     */
    public function render(): View
    {
        /** @var LengthAwarePaginator<int, DatabaseNotification> $notifications */
        $notifications = Auth::user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(10, pageName: 'notificationPage');

        return view('livewire.notification-center', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Updates the unread notification count.
     */
    private function loadUnreadCount(): void
    {
        $this->unreadCount = Auth::user()->unreadNotifications()->count();
    }
}
