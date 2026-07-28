<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\UserNotificationsChanged;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cache-backed change marker for a user's stored notifications.
 */
final class NotificationsToken
{
    /**
     * Get the current token for the user, minting a new one when none is cached.
     */
    public static function current(int $userId): string
    {
        return Cache::remember(self::key($userId), now()->addHour(), fn (): string => Str::random(16));
    }

    /**
     * Forget the user's token so the next read mints a fresh one.
     */
    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /**
     * Forget the user's token, broadcast the change to their open tabs, and log a warning when the broadcast fails.
     */
    public static function flush(int $userId): void
    {
        self::forget($userId);

        try {
            event(new UserNotificationsChanged($userId));
        } catch (BroadcastException $broadcastException) {
            Log::warning('Notifications broadcast failed; realtime update skipped.', [
                'event' => UserNotificationsChanged::class,
                'user_id' => $userId,
                'exception' => $broadcastException->getMessage(),
            ]);
        }
    }

    /**
     * Build the cache key for the user's token.
     */
    private static function key(int $userId): string
    {
        return sprintf('user:%d:notifications-token', $userId);
    }
}
