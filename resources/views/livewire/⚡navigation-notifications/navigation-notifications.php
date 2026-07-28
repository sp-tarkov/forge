<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\NotificationPresentationService;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Support\NotificationsToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /**
     * Current count of unread notifications for the authenticated user.
     */
    public int $unreadCount = 0;

    /**
     * Notifications change token the component last rendered with.
     */
    #[Locked]
    public string $notificationsToken = '';

    /**
     * Initialize the component when it's first mounted.
     */
    public function mount(): void
    {
        $this->loadUnreadCount();
    }

    /**
     * Map the user's private broadcast channel to the notification refresh handler.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        return [
            sprintf('echo-private:user.%d,UserNotificationsChanged', $userId) => 'refreshNotifications',
        ];
    }

    /**
     * Refresh the notification state, skipping the render when nothing has changed.
     */
    public function refreshNotifications(): void
    {
        $user = Auth::user();

        if (! $user || NotificationsToken::current($user->id) === $this->notificationsToken) {
            $this->skipRender();

            return;
        }

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

        $notification = $this->findNotification($user, $notificationId);

        if (! $notification instanceof DatabaseNotification) {
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

        $notification = $this->findNotification($user, $notificationId);

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
        NotificationsToken::flush($user->id);
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

        $notification = $this->findNotification($user, $notificationId);

        if ($notification instanceof DatabaseNotification) {
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
        NotificationsToken::flush($user->id);
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
     * Find one of the user's notifications by id, returning null when the id is not a valid UUID.
     */
    private function findNotification(User $user, string $notificationId): ?DatabaseNotification
    {
        if (! Str::isUuid($notificationId)) {
            return null;
        }

        return $user->notifications()->find($notificationId);
    }

    /**
     * Updates the unread notification count and adopts the current notifications change token.
     */
    private function loadUnreadCount(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->notificationsToken = NotificationsToken::current($user->id);
        $this->unreadCount = $user->unreadNotifications()->count();
    }
};
