<?php

declare(strict_types=1);

use App\Console\Commands\CleanupOldNotificationLogs;
use App\Console\Commands\EnsureFavouritesLists;
use App\Console\Commands\ForgeHeartbeat;
use App\Console\Commands\UpdateGeoLiteDatabase;
use App\Jobs\AggregateApiUsageDailyJob;
use App\Jobs\AggregateApiUsageJob;
use App\Jobs\DetectDownloadChangesJob;
use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Jobs\ProcessPinnedModVersionPublishDates;
use App\Jobs\SendDiscordNotifications;
use App\Jobs\UpdateDisposableEmailBlocklist;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command(CleanupOldNotificationLogs::class)->daily()->onOneServer();
Schedule::command(EnsureFavouritesLists::class)->daily()->onOneServer();

Schedule::command(UpdateGeoLiteDatabase::class)->daily()->at('02:00')->onOneServer()->runInBackground()->environments('production');
Schedule::job(new UpdateDisposableEmailBlocklist)->daily()->at('04:00')->onOneServer()->environments('production');

Schedule::job(new SendDiscordNotifications)->everyMinute()->onOneServer()->withoutOverlapping()->environments('production');
Schedule::job(new ProcessPinnedModVersionPublishDates)->everyMinute()->onOneServer()->withoutOverlapping()->environments('production');

if (config('app.forge_heartbeat_url')) {
    Schedule::command(ForgeHeartbeat::class)->everyMinute()->onOneServer()->withoutOverlapping();
}

if (config('verification.enabled')) {
    Schedule::job(new DetectDownloadChangesJob)->twiceDaily(6, 18)->onOneServer()->withoutOverlapping();
}

// Drain the API usage counters every minute and roll them up daily. Gated on the same flag that enables recording so
// capture and aggregation are turned on together.
if (config('api.usage.enabled')) {
    Schedule::job(new AggregateApiUsageJob)->everyMinute()->onOneServer()->withoutOverlapping();
    Schedule::job(new AggregateApiUsageDailyJob)->dailyAt('00:15')->onOneServer();
}

// Refresh the Cloudflare edge request totals shown in the footer. Origin counters miss everything Cloudflare serves
// from cache, so this fills in the full picture. Scheduled whenever the analytics credentials are present, regardless
// of environment, so any environment pointed at a Cloudflare zone reflects real edge traffic.
if (config('services.cloudflare.analytics_token') && config('services.cloudflare.zone_id')) {
    Schedule::job(new FetchCloudflareApiAnalyticsJob)->everyFiveMinutes()->onOneServer()->withoutOverlapping();
}

if (app()->isLocal() && config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
