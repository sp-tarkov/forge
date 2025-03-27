<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Import\ImportHubJob;
use App\Jobs\ResolveDependenciesJob;
use App\Jobs\ResolveSptVersionsJob;
use App\Jobs\SearchSyncJob;
use App\Jobs\SptVersionModCountsJob;
use App\Jobs\UpdateModDownloadsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

class ImportHubCommand extends Command
{
    protected $signature = 'app:import-hub';

    protected $description = 'Connects to the Hub database and import the data into the Forge database';

    public function handle(): void
    {
        Bus::chain([
            (new ImportHubJob)->onQueue('long'),
            new ResolveSptVersionsJob,
            new ResolveDependenciesJob,
            new SptVersionModCountsJob,
            new UpdateModDownloadsJob,
            (new SearchSyncJob)->onQueue('long')->delay(Carbon::now()->addSeconds(30)),
            fn () => Artisan::call('cache:clear'),
        ])->dispatch();

        $this->info('The import-hub bus chain has been added to the queue');
    }
}
