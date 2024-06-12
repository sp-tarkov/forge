<?php

namespace App\Console\Commands;

use App\Jobs\ImportHubData;
use Illuminate\Console\Command;

class ImportHub extends Command
{
    protected $signature = 'app:import-hub';

    protected $description = 'Connects to the Hub database and imports the data into the Laravel database.';

    public function handle(): void
    {
        // Add the ImportHubData job to the queue.
        ImportHubData::dispatch()->onQueue('long');

        $this->info('The import job has been added to the queue.');
    }
}
