<?php

namespace App\Console\Commands;

use App\Jobs\SptVersionModCountsJob;
use Illuminate\Console\Command;

class SptVersionModCountsCommand extends Command
{
    protected $signature = 'app:count-mods';

    protected $description = 'Recalculate the mod counts for each SPT version.';

    public function handle(): void
    {
        SptVersionModCountsJob::dispatch()->onQueue('default');

        $this->info('The count job has been added to the queue.');
    }
}
