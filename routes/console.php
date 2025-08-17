<?php

declare(strict_types=1);

use App\Console\Commands\CleanupOldNotificationLogs;
use App\Console\Commands\ImportHubCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHubCommand::class)->everyThirtyMinutes();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command(CleanupOldNotificationLogs::class)->daily();

if (config('app.env') === 'local' && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
