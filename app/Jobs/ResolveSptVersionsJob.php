<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
final class ResolveSptVersionsJob implements ShouldBeUnique, ShouldQueue
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
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $sptVersionService = new SptVersionService;

        ModVersion::query()
            ->chunk(100, function (Collection $modVersions) use ($sptVersionService): void {
                foreach ($modVersions as $modVersion) {
                    $sptVersionService->resolve($modVersion);
                }
            });
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
