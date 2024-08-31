<?php

use App\Console\Commands\ImportHubCommand;
use App\Console\Commands\ResolveVersionsCommand;
use App\Console\Commands\SptVersionModCountsCommand;
use App\Console\Commands\UpdateModDownloadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHubCommand::class)->hourly();
Schedule::command(ResolveVersionsCommand::class)->hourlyAt(30);
Schedule::command(SptVersionModCountsCommand::class)->hourlyAt(40);
Schedule::command(UpdateModDownloadsCommand::class)->hourlyAt(45);

Schedule::command('horizon:snapshot')->everyFiveMinutes();
