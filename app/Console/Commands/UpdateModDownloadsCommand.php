<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Jobs\UpdateDownloadsJob;
use Illuminate\Console\Command;

#[Description('Recalculate total downloads for all mods and addons')]
#[Signature('app:update-downloads')]
final class UpdateModDownloadsCommand extends Command
{
    public function handle(): void
    {
        dispatch(new UpdateDownloadsJob())->onQueue('default');

        $this->info('UpdateDownloadsJob added to the queue');
    }
}
