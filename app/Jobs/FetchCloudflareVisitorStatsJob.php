<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Visitor;
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
 * Pulls the count of people who loaded a page in the last few minutes from Cloudflare and caches it for the footer.
 */
#[Timeout(30)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class FetchCloudflareVisitorStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The cache key holding the most recent online count.
     */
    public const string CACHE_KEY = 'cloudflare_visitors_online';

    /**
     * How long a fetched count stays valid in the cache.
     */
    private const int CACHE_TTL_MINUTES = 20;

    /**
     * Execute the job.
     */
    public function handle(CloudflareAnalyticsService $analytics): void
    {
        $visitors = $analytics->visitorsOnline();

        if ($visitors === null) {
            return;
        }

        Cache::put(self::CACHE_KEY, $visitors, now()->addMinutes(self::CACHE_TTL_MINUTES));

        Visitor::recordPeak($visitors['count']);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('FetchCloudflareVisitorStatsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
