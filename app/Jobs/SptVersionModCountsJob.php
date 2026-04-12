<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SptVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class SptVersionModCountsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
