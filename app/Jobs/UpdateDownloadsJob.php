<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDownloadsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
}
