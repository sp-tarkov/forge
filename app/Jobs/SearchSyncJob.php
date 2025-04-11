<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

class SearchSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Bus::chain([
            fn () => Artisan::call('cache:clear'),
            (new ResolveSptVersionsJob)->onQueue('long'),
            fn () => Artisan::call('scout:delete-all-indexes'),
            fn () => Artisan::call('scout:sync-index-settings'),
            fn () => Artisan::call('scout:import', ['model' => Mod::class]),
            fn () => Artisan::call('scout:import', ['model' => User::class]),
        ])->dispatch();
    }
}
