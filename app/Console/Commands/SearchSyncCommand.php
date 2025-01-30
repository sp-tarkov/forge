<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SearchSyncCommand extends Command
{
    protected $signature = 'app:search-sync';

    protected $description = 'Syncs all search settings and indexes with the database data';

    public function handle(): void
    {
        Artisan::call('scout:delete-all-indexes');
        Artisan::call('scout:sync-index-settings');
        Artisan::call('scout:import', ['model' => Mod::class]);
        Artisan::call('scout:import', ['model' => User::class]);

        $this->info('The search synchronisation jobs have been added to the queue');
    }
}
