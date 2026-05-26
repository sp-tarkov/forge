<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Jobs\SearchSyncJob;
use Illuminate\Console\Command;

#[Description('Syncs all search settings and indexes with the database data')]
#[Signature('app:search-sync')]
final class SearchSyncCommand extends Command
{
    public function handle(): void
    {
        dispatch(new SearchSyncJob())->onQueue('default');

        $this->info('The search synchronization job has been added to the queue');
    }
}
