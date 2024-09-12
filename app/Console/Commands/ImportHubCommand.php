<?php

namespace App\Console\Commands;

use App\Jobs\Import\ImportHubDataJob;
use Illuminate\Console\Command;

class ImportHubCommand extends Command
{
    protected $signature = 'app:import-hub';

    protected $description = 'Connects to the Hub database and imports the data into the Laravel database';

    public function handle(): void
    {
        ImportHubDataJob::dispatch()->onQueue('long');

        $this->info('ImportHubDataJob has been added to the queue');
    }
}
