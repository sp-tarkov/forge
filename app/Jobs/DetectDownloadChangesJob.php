<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Dispatches individual CheckDownloadLinkJob instances for all published mod/addon versions.
 */
#[Timeout(120)]
#[Tries(3)]
final class DetectDownloadChangesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $queue = config()->string('verification.change_detection_queue', 'verification-detection');

        $this->dispatchChecks(
            ModVersion::query()
                ->whereNotNull('published_at')
                ->where('disabled', false)
                ->where('link', '!=', ''),
            ModVersion::class,
            $queue,
        );

        $this->dispatchChecks(
            AddonVersion::query()
                ->whereNotNull('published_at')
                ->where('disabled', false)
                ->where('link', '!=', ''),
            AddonVersion::class,
            $queue,
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('DetectDownloadChangesJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Push a CheckDownloadLinkJob for each version matching the query onto the queue in per-chunk bulk batches.
     *
     * @param  Builder<ModVersion>|Builder<AddonVersion>  $query
     * @param  class-string<ModVersion>|class-string<AddonVersion>  $modelClass
     */
    private function dispatchChecks(Builder $query, string $modelClass, string $queue): void
    {
        $query->select('id')->chunkById(500, function (Collection $versions) use ($modelClass, $queue): void {
            $jobs = $versions
                ->map(fn (ModVersion|AddonVersion $version): CheckDownloadLinkJob => new CheckDownloadLinkJob($modelClass, $version->id))
                ->all();

            Queue::bulk($jobs, '', $queue);
        });
    }
}
