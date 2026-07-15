<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DetectDownloadChangesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Manually trigger the download change detection sweep that queues re-verification for changed files.
 */
#[Signature('app:detect-download-changes')]
#[Description('Queue the download change detection sweep for all published mod and addon versions')]
final class DetectDownloadChangesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        dispatch(new DetectDownloadChangesJob);

        $this->info('DetectDownloadChangesJob has been added to the queue.');

        return self::SUCCESS;
    }
}
