<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VerificationStatus;
use App\Models\VerificationResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

/**
 * Marks verification results stuck in the Pending or Running status as errored once they exceed the configured stale
 * thresholds, so abandoned runs stop blocking future verification dispatches for the same version.
 */
#[Timeout(120)]
#[Tries(1)]
final class CleanupStaleVerificationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $staleCount = $this->markStale(
            VerificationStatus::Pending,
            now()->subMinutes(config()->integer('verification.stale.pending_minutes', 1440)),
        );

        $staleCount += $this->markStale(
            VerificationStatus::Running,
            now()->subMinutes(config()->integer('verification.stale.running_minutes', 60)),
        );

        if ($staleCount > 0) {
            Log::warning('CleanupStaleVerificationsJob marked stale verification results as errored', [
                'count' => $staleCount,
            ]);
        }
    }

    /**
     * Mark all results in the given status last updated before the cutoff as errored. Returns the number of rows
     * updated. The denormalized status on the version model is intentionally left untouched because it only ever
     * holds final statuses and a newer verification may have completed since the stale row was created.
     */
    private function markStale(VerificationStatus $status, CarbonImmutable $cutoff): int
    {
        return VerificationResult::query()
            ->where('status', $status)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => VerificationStatus::Error,
                'failure_reason' => 'Verification never completed and was marked as stale',
                'completed_at' => now(),
            ]);
    }
}
