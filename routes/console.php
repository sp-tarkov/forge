<?php

declare(strict_types=1);

use App\Console\Commands\CleanupOldNotificationLogs;
use App\Console\Commands\ForgeHeartbeat;
use App\Console\Commands\UpdateGeoLiteDatabase;
use App\Jobs\SendModDiscordNotifications;
use App\Jobs\UpdateDisposableEmailBlocklist;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command(CleanupOldNotificationLogs::class)->daily();
Schedule::command(UpdateGeoLiteDatabase::class)->daily()->at('02:00');
Schedule::job(new UpdateDisposableEmailBlocklist)->daily()->at('04:00');

// Discord notifications for mods and mod versions
Schedule::job(new SendModDiscordNotifications)->everyMinute();

if (config('app.forge_heartbeat_url')) {
    Schedule::command(ForgeHeartbeat::class)->everyMinute();
}

if (config('app.env') === 'local' && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
