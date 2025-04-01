<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Import\ImportHubJob;
use Illuminate\Console\Command;

class ImportHubCommand extends Command
{
    protected $signature = 'app:import-hub';

    protected $description = 'Connects to the Hub database and import the data into the Forge database';

    public function handle(): void
    {
        ImportHubJob::dispatch()->onQueue('long');

        $this->info('The import-hub job has been added to the queue');
    }
}
