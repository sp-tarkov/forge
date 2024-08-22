<?php

use App\Console\Commands\ImportHubCommand;
use App\Console\Commands\ResolveVersionsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHubCommand::class)->hourly();
Schedule::command(ResolveVersionsCommand::class)->hourlyAt(30);

Schedule::command('horizon:snapshot')->everyFiveMinutes();
