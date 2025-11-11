<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateDownloadsJob;
use Illuminate\Console\Command;

class UpdateModDownloadsCommand extends Command
{
    protected $signature = 'app:update-downloads';

    protected $description = 'Recalculate total downloads for all mods and addons';

    public function handle(): void
    {
        dispatch(new UpdateDownloadsJob())->onQueue('default');

        $this->info('UpdateDownloadsJob added to the queue');
    }
}
