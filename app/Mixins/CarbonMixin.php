<?php

declare(strict_types=1);

namespace App\Mixins;

use Carbon\Carbon;
use Closure;

/**
 * Mixin for Carbon date instances.
 *
 * @mixin Carbon
 */
class CarbonMixin
{
    /**
     * Dynamically format dates based on how much time has passed.
     */
    public function dynamicFormat(): Closure
    {
        /**
         * @param  bool  $includeTime  Whether to include time in the format
         * @param  bool  $verbose  Whether to use verbose formatting
         */
        return function (bool $includeTime = true, bool $verbose = true): string {
            // Get the user's timezone, fallback to app timezone if not authenticated or no timezone set
            $userTimezone = auth()->user() ? auth()->user()->timezone : null;
            $userTimezone ??= config('app.timezone', 'UTC');

            // Convert the date to the user's timezone
            $localDate = $this->copy()->setTimezone($userTimezone);
            $now = now()->setTimezone($userTimezone);

            // Today
            if ($localDate->isToday()) {
                return $localDate->format('g:i A');
            }

            // Yesterday
            if ($localDate->isYesterday()) {
                if (! $includeTime) {
                    return 'Yesterday';
                }

                return $verbose ? 'Yesterday at '.$localDate->format('g:i A') : 'Yesterday';
            }

            // Within the last week
            if ($localDate->isAfter($now->copy()->subWeek()) && $localDate->isBefore($now)) {
                if (! $includeTime) {
                    return $localDate->format('l'); // "Monday"
                }

                return $verbose
                    ? $localDate->format('l \a\t g:i A') // "Monday at 2:30 PM"
                    : $localDate->format('l, g:i A'); // "Monday, 2:30 PM"
            }

            // This year
            if ($localDate->format('Y') === $now->format('Y')) {
                if (! $includeTime) {
                    return $localDate->format('M j'); // "Mar 15"
                }

                return $localDate->format('M j, g:i A'); // "Mar 15, 2:30 PM"
            }

            // Different year
            if (! $includeTime) {
                return $localDate->format('M j, Y'); // "Mar 15, 2023"
            }

            return $localDate->format('M j, Y \a\t g:i A'); // "Mar 15, 2023 at 2:30 PM"
        };
    }
}
