<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\VerificationSweepJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Manually trigger the verification sweep that queues re-verification for changed or outdated files.
 */
#[Signature('app:verification-sweep')]
#[Description('Queue the verification sweep for all published mod and addon versions')]
final class VerificationSweepCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        dispatch(new VerificationSweepJob);

        $this->info('VerificationSweepJob has been added to the queue.');

        return self::SUCCESS;
    }
}
