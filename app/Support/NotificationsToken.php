<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\UserNotificationsChanged;
use Illuminate\Support\Facades\Cache;
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
     * Forget the user's token and broadcast the change to their open tabs.
     */
    public static function flush(int $userId): void
    {
        self::forget($userId);

        event(new UserNotificationsChanged($userId));
    }

    /**
     * Build the cache key for the user's token.
     */
    private static function key(int $userId): string
    {
        return sprintf('user:%d:notifications-token', $userId);
    }
}
