<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Support\NotificationsToken;
use Illuminate\Notifications\DatabaseNotification;

final readonly class DatabaseNotificationObserver
{
    /**
     * Handle the DatabaseNotification "created" event.
     */
    public function created(DatabaseNotification $notification): void
    {
        $this->flushToken($notification);
    }

    /**
     * Handle the DatabaseNotification "updated" event.
     */
    public function updated(DatabaseNotification $notification): void
    {
        $this->flushToken($notification);
    }

    /**
     * Handle the DatabaseNotification "deleted" event.
     */
    public function deleted(DatabaseNotification $notification): void
    {
        $this->flushToken($notification);
    }

    /**
     * Flush the notifications change token for the notified user.
     */
    private function flushToken(DatabaseNotification $notification): void
    {
        if ($notification->notifiable_type === User::class) {
            NotificationsToken::flush((int) $notification->notifiable_id);
        }
    }
}
