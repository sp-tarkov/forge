<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SptVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(120)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class ResolveSptVersionsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(SptVersionService $sptVersionService): void
    {
        $sptVersionService->resolveAll();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ResolveSptVersionsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
