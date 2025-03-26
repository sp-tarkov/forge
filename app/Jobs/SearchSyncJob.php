<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class SearchSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Artisan::call('scout:delete-all-indexes');
        Artisan::call('scout:sync-index-settings');
        Artisan::call('scout:import', ['model' => Mod::class]);
        Artisan::call('scout:import', ['model' => User::class]);
    }
}
