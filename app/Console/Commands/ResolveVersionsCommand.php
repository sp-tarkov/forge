<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ResolveAddonVersionsJob;
use App\Jobs\ResolveDependenciesJob;
use App\Jobs\ResolveSptVersionsJob;
use Illuminate\Console\Command;

class ResolveVersionsCommand extends Command
{
    protected $signature = 'app:resolve-versions';

    protected $description = 'Resolve SPT and dependency versions for all mods and addon versions';

    public function handle(): void
    {
        dispatch(new ResolveSptVersionsJob())->onQueue('default');
        dispatch(new ResolveDependenciesJob())->onQueue('default');
        dispatch(new ResolveAddonVersionsJob())->onQueue('default');

        $this->info('ResolveSptVersionsJob, ResolveDependenciesJob, and ResolveAddonVersionsJob have been added to the queue');
    }
}
