<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class SearchSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Bus::chain([
            new ArtisanCallJob('cache:clear'),
            (new ResolveSptVersionsJob)->onQueue('long'),
            new ArtisanCallJob('scout:delete-all-indexes'),
            new ArtisanCallJob('scout:sync-index-settings'),
            new ArtisanCallJob('scout:import', ['model' => Mod::class]),
            new ArtisanCallJob('scout:import', ['model' => User::class]),
        ])->dispatch();
    }
}
