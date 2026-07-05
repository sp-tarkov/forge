<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Computes the visitor analytics statistics for one filter combination on the dedicated analytics queue and stores
 * the payload in the cache so the admin page can poll for it instead of running the heavy aggregates in-request.
 */
#[Timeout(300)]
#[Tries(1)]
final class ComputeVisitorAnalyticsStatsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VisitorAnalyticsFilters $filters,
    ) {
        $this->onQueue(config()->string('visitor-analytics.queue', 'visitor-analytics'));
    }

    public function handle(VisitorAnalyticsService $service): void
    {
        $service->markProcessing($this->filters);

        $service->storeStats($this->filters, $service->computeStats($this->filters));
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        resolve(VisitorAnalyticsService::class)
            ->markFailed($this->filters, $exception?->getMessage() ?? 'The stats computation failed.');
    }
}
