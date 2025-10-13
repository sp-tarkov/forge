<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SptVersionModCountsJob;
use Illuminate\Console\Command;

class SptVersionModCountsCommand extends Command
{
    protected $signature = 'app:count-mods';

    protected $description = 'Recalculate the mod counts for each SPT version';

    public function handle(): void
    {
        dispatch(new SptVersionModCountsJob())->onQueue('default');

        $this->info('SptVersionModCountsJob has been added to the queue');
    }
}
