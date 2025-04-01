<?php

declare(strict_types=1);

use App\Console\Commands\ImportHubCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHubCommand::class)->everyThirtyMinutes();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
