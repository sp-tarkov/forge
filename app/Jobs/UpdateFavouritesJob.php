<?php

declare(strict_types=1);

namespace App\Jobs;

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
final class UpdateFavouritesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Recalculate the denormalized favourite counts for each mod.
     */
    public function handle(): void
    {
        Mod::query()
            ->chunk(100, function (Collection $mods): void {
                foreach ($mods as $mod) {
                    $mod->calculateFavourites();
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateFavouritesJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
