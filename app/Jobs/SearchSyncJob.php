<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(300)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class SearchSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
