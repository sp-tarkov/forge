<?php

declare(strict_types=1);

use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;

return [
    /*
     * The webhook URLs that we'll use to send a message to Discord.
     */
    'webhook_urls' => [
        'default' => env('DISCORD_ALERT_WEBHOOK'),
        'mods' => env('DISCORD_MODS_WEBHOOK'),
    ],

    /*
     * The Discord role ID to mention for mod notifications.
     * Format: Role ID without the <@&> wrapper
     */
    'mod_notifications_role_id' => env('DISCORD_MOD_NOTIFICATIONS_ROLE_ID'),

    /*
     * Default avatar is an empty string '' which means it will not be included in the payload.
     * You can add multiple custom avatars and then specify directly with withAvatar()
     */
    'avatar_urls' => [
        'default' => '',
    ],

    /*
     * This job will send the message to Discord. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => SendToDiscordChannelJob::class,

    /*
    * The queue name that should be used to send the alert. Only supported for drivers
    * that allow multiple queues (e.g., redis, database, beanstalkd). Ignored for sync and null drivers.
    */
    'queue' => env('DISCORD_ALERT_QUEUE', 'default'),
];
