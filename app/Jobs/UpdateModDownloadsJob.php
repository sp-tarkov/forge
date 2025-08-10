<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateModDownloadsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Recalculate the total download counts for each mod.
     */
    public function handle(): void
    {
        Mod::query()
            ->with('versions')
            ->chunk(100, function (Collection $mods): void {
                foreach ($mods as $mod) {
                    $mod->calculateDownloads();
                }
            });
    }
}
