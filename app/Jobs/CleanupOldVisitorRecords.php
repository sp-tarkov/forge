<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TrackingEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupOldVisitorRecords implements ShouldQueue
{
    use Queueable;

    /**
     * The maximum number of records to delete per batch.
     */
    private const int BATCH_SIZE = 1000;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $retentionMonths = config('tracking.retention_months', 36);
        $cutoffDate = now()->subMonths($retentionMonths);
        $totalDeleted = 0;

        Log::info('Starting cleanup of visitor records older than '.$cutoffDate->toDateString());

        TrackingEvent::query()
            ->where('created_at', '<', $cutoffDate)
            ->chunk(self::BATCH_SIZE, function (Collection $events) use (&$totalDeleted): void {
                $ids = $events->pluck('id')->toArray();
                $deleted = TrackingEvent::query()->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
            });

        Log::info(sprintf('Visitor record cleanup completed. Deleted %d records older than %s months.', $totalDeleted, $retentionMonths));
    }
}
