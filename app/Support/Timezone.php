<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeZone;
use Throwable;

final class Timezone
{
    /**
     * Resolve a user-supplied timezone string to a usable IANA identifier,
     * falling back to the configured app timezone when the input is empty or
     * not recognized by the current PHP timezonedb.
     *
     * Existing rows in the database may contain deprecated aliases (e.g.
     * Europe/Kiev, Asia/Calcutta, America/Buenos_Aires) that newer
     * timezonedb releases no longer accept. Returning the app timezone keeps
     * date rendering working instead of throwing a 500.
     */
    public static function resolve(?string $timezone): string
    {
        $fallback = config()->string('app.timezone', 'UTC');

        if ($timezone === null || $timezone === '') {
            return $fallback;
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            return $fallback;
        }

        return $timezone;
    }
}
