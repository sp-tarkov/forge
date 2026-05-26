<?php

declare(strict_types=1);

namespace App\Traits;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Throttles a queued notification's mail channel so the whole application
 * stays within the shared SES sending quota.
 */
trait ThrottlesOutboundEmail
{
    /**
     * The maximum number of unhandled exceptions to allow before the queued
     * notification is failed. Rate-limited releases are not exceptions and
     * never count towards this.
     */
    public int $maxExceptions = 3;

    /**
     * Get the job middleware the queued notification should pass through.
     *
     * The SES rate limiter is applied only to the mail channel so the other
     * channels (such as database) are never throttled. When the limit is
     * reached the mail job is released back onto the queue rather than sent,
     * so no worker time is spent waiting on a send slot.
     *
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new RateLimited('outbound-email')] : [];
    }

    /**
     * Determine the time at which the queued notification should stop retrying.
     *
     * The rate limiter releases over-quota jobs back onto the queue, which
     * increments the attempt count, so a time-based deadline is used instead
     * of a fixed try count that the throttled retries would quickly exhaust.
     * Evaluated once when the job is dispatched and frozen into its payload.
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(3);
    }
}
