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
        $this->dispatchChecks(
            ModVersion::query()
                ->whereNotNull('published_at')
                ->where('disabled', false)
                ->where('link', '!=', ''),
            ModVersion::class,
        );

        $this->dispatchChecks(
            AddonVersion::query()
                ->whereNotNull('published_at')
                ->where('disabled', false)
                ->where('link', '!=', ''),
            AddonVersion::class,
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
     * Dispatch CheckDownloadLinkJob for each version matching the query.
     *
     * @param  Builder<ModVersion>|Builder<AddonVersion>  $query
     * @param  class-string<ModVersion>|class-string<AddonVersion>  $modelClass
     */
    private function dispatchChecks(Builder $query, string $modelClass): void
    {
        $query->select('id')->chunkById(100, function (Collection $versions) use ($modelClass): void {
            /** @var ModVersion|AddonVersion $version */
            foreach ($versions as $version) {
                dispatch(new CheckDownloadLinkJob($modelClass, $version->id))
                    ->onQueue(config()->string('verification.change_detection_queue', 'verification-detection'));
            }
        });
    }
}
