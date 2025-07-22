<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SearchSyncJob;
use Illuminate\Console\Command;

class SearchSyncCommand extends Command
{
    protected $signature = 'app:search-sync';

    protected $description = 'Syncs all search settings and indexes with the database data';

    public function handle(): void
    {
        SearchSyncJob::dispatch()->onQueue('default');

        $this->info('The search synchronization job has been added to the queue');
    }
}
