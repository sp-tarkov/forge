<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SptVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
final class SptVersionModCountsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * Recalculate the mod counts for each SPT version.
     */
    public function handle(): void
    {
        SptVersion::query()
            ->get()
            ->each(function (SptVersion $sptVersion): void {
                $sptVersion->updateModCount();
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SptVersionModCountsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
