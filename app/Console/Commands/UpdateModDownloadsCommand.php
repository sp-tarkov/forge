<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateModDownloadsJob;
use Illuminate\Console\Command;

class UpdateModDownloadsCommand extends Command
{
    protected $signature = 'app:update-downloads';

    protected $description = 'Recalculate total downloads for all mods';

    public function handle(): void
    {
        UpdateModDownloadsJob::dispatch()->onQueue('default');

        $this->info('UpdateModDownloadsJob added to the queue');
    }
}
