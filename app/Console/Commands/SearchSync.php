<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SearchSync extends Command
{
    protected $signature = 'app:search-sync';

    protected $description = 'Syncs all search settings and indexes with the database data.';

    public function handle(): void
    {
        Artisan::call('scout:delete-all-indexes');
        Artisan::call('scout:sync-index-settings');
        Artisan::call('scout:import', ['model' => '\App\Models\Mod']);
        Artisan::call('scout:import', ['model' => '\App\Models\User']);

        $this->info('The search synchronisation jobs have been added to the queue.');
    }
}
