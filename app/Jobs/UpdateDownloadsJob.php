<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class UpdateDownloadsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Recalculate the total download counts for each mod and addon.
     */
    public function handle(): void
    {
        // Update mod download counts
        Mod::query()
            ->with('versions')
            ->chunk(100, function (Collection $mods): void {
                foreach ($mods as $mod) {
                    $mod->calculateDownloads();
                }
            });

        // Update addon download counts
        Addon::query()
            ->with('versions')
            ->chunk(100, function (Collection $addons): void {
                foreach ($addons as $addon) {
                    $addon->calculateDownloads();
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateDownloadsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
