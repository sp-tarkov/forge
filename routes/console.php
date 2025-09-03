<?php

declare(strict_types=1);

use App\Console\Commands\CleanupOldNotificationLogs;
use App\Console\Commands\ImportHubCommand;
use App\Console\Commands\UpdateGeoLiteDatabase;
use App\Jobs\CleanupOldVisitorRecords;
use App\Jobs\UpdateDisposableEmailBlocklist;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportHubCommand::class)->everyThirtyMinutes();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command(CleanupOldNotificationLogs::class)->daily();
Schedule::job(new CleanupOldVisitorRecords)->daily()->at('03:00');
Schedule::command(UpdateGeoLiteDatabase::class)->daily()->at('02:00');
Schedule::command('visitors:clean --hours=6')->hourly();
Schedule::job(new UpdateDisposableEmailBlocklist)->daily()->at('04:00');

if (config('app.env') === 'local' && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
