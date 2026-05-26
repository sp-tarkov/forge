<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ResolveAddonVersionsJob;
use App\Jobs\ResolveDependenciesJob;
use App\Jobs\ResolveSptVersionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Resolve SPT and dependency versions for all mods and addon versions')]
#[Signature('app:resolve-versions')]
final class ResolveVersionsCommand extends Command
{
    public function handle(): void
    {
        dispatch(new ResolveSptVersionsJob())->onQueue('default');
        dispatch(new ResolveDependenciesJob())->onQueue('default');
        dispatch(new ResolveAddonVersionsJob())->onQueue('default');

        $this->info('ResolveSptVersionsJob, ResolveDependenciesJob, and ResolveAddonVersionsJob have been added to the queue');
    }
}
