<?php

declare(strict_types=1);

use App\Services\NotificationPresentationService;
use App\Support\DataTransferObjects\NotificationPresentation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
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
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return;
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
            $this->loadUnreadCount();
        }

        $url = $this->presentationFor($notification)->url;

        if ($url) {
            $this->redirect($url, navigate: true);
        }
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
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->update(['read_at' => now()]);
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
        $this->loadUnreadCount();
    }

    /**
     * Build the nav-dropdown presentation for a stored notification.
     */
    public function presentationFor(DatabaseNotification $notification): NotificationPresentation
    {
        return resolve(NotificationPresentationService::class)->present($notification);
    }

    /**
     * Fetch recent unread notifications for the authenticated user.
     *
     * @return Collection<int, DatabaseNotification>
     */
    /** @phpstan-ignore missingType.generics */
    #[Computed]
    public function notifications(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            return new Collection();
        }

        /** @var Collection<int, DatabaseNotification> */
        return $user->unreadNotifications()->orderBy('created_at', 'desc')->limit(10)->get();
    }

    /**
     * Updates the unread notification count.
     */
    private function loadUnreadCount(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->unreadCount = $user->unreadNotifications()->count();
    }
};
