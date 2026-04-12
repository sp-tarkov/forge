<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(300)]
final class SearchSyncJob implements ShouldBeUnique, ShouldQueue
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

    public function handle(): void
    {
        Bus::chain([
            new ArtisanCallJob('cache:clear'),
            (new ResolveSptVersionsJob)->onQueue('long'),
            new ArtisanCallJob('scout:delete-all-indexes'),
            new ArtisanCallJob('scout:sync-index-settings'),
            new ArtisanCallJob('scout:import', ['model' => Addon::class]),
            new ArtisanCallJob('scout:import', ['model' => Mod::class]),
            new ArtisanCallJob('scout:import', ['model' => User::class]),
        ])->dispatch();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SearchSyncJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
