<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SptVersionModCountsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Recalculate the mod counts for each SPT version')]
#[Signature('app:count-mods')]
final class SptVersionModCountsCommand extends Command
{
    public function handle(): void
    {
        dispatch(new SptVersionModCountsJob())->onQueue('default');

        $this->info('SptVersionModCountsJob has been added to the queue');
    }
}
