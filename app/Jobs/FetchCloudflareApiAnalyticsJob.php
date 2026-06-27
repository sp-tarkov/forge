<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CloudflareAnalyticsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the open API's edge request totals from Cloudflare and caches them for the footer.
 *
 * The footer renders on every navigation, so it must never make this external call itself. This scheduled job does the
 * fetching off the request path and writes a small scalar array to the cache that the footer simply reads. When the
 * fetch yields nothing (Cloudflare not configured, or a transient failure) the previously cached value is left intact
 * rather than being blanked, so a brief Cloudflare hiccup does not flap the footer back to the origin-only number.
 */
#[Timeout(30)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class FetchCloudflareApiAnalyticsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The cache key holding the most recent Cloudflare edge totals. The footer reads this key directly.
     */
    public const string CACHE_KEY = 'cloudflare_api_usage_24h';

    /**
     * How long a fetched value stays valid. Comfortably longer than the schedule interval so a couple of failed runs do
     * not expire the last good value; once it does lapse the footer falls back to the origin-only count.
     */
    private const int CACHE_TTL_MINUTES = 15;

    /**
     * Execute the job.
     */
    public function handle(CloudflareAnalyticsService $analytics): void
    {
        $usage = $analytics->apiUsageLast24Hours();

        if ($usage === null) {
            return;
        }

        Cache::put(self::CACHE_KEY, $usage, now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('FetchCloudflareApiAnalyticsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
