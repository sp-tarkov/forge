<?php

declare(strict_types=1);

use App\Console\Commands\CleanupOldNotificationLogs;
use App\Console\Commands\ForgeHeartbeat;
use App\Console\Commands\UpdateGeoLiteDatabase;
use App\Jobs\ProcessPinnedModVersionPublishDates;
use App\Jobs\SendDiscordNotifications;
use App\Jobs\UpdateDisposableEmailBlocklist;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command(CleanupOldNotificationLogs::class)->daily()->onOneServer();

Schedule::command(UpdateGeoLiteDatabase::class)->daily()->at('02:00')->onOneServer()->runInBackground();
Schedule::job(new UpdateDisposableEmailBlocklist)->daily()->at('04:00')->onOneServer();

Schedule::job(new SendDiscordNotifications)->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::job(new ProcessPinnedModVersionPublishDates)->everyMinute()->onOneServer()->withoutOverlapping();

if (config('app.forge_heartbeat_url')) {
    Schedule::command(ForgeHeartbeat::class)->everyMinute()->onOneServer()->withoutOverlapping();
}

if (app()->isLocal() && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
