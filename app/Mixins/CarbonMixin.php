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
            $now = now();

            // Today
            if ($this->isToday()) {
                return $this->format('g:i A');
            }

            // Yesterday
            if ($this->isYesterday()) {
                if (! $includeTime) {
                    return 'Yesterday';
                }

                return $verbose ? 'Yesterday at '.$this->format('g:i A') : 'Yesterday';
            }

            // Within the last week
            if ($this->isAfter($now->copy()->subWeek()) && $this->isBefore($now)) {
                if (! $includeTime) {
                    return $this->format('l'); // "Monday"
                }

                return $verbose
                    ? $this->format('l \a\t g:i A') // "Monday at 2:30 PM"
                    : $this->format('l, g:i A'); // "Monday, 2:30 PM"
            }

            // This year
            if ($this->format('Y') === $now->format('Y')) {
                if (! $includeTime) {
                    return $this->format('M j'); // "Mar 15"
                }

                return $this->format('M j, g:i A'); // "Mar 15, 2:30 PM"
            }

            // Different year
            if (! $includeTime) {
                return $this->format('M j, Y'); // "Mar 15, 2023"
            }

            return $this->format('M j, Y \a\t g:i A'); // "Mar 15, 2023 at 2:30 PM"
        };
    }
}
