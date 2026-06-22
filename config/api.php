<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting (origin fallback)
    |--------------------------------------------------------------------------
    |
    | Cloudflare is the primary rate limiter for the open v0 API at the edge. This origin-side limiter is a
    | deliberately lax safety net: it only bites traffic that reaches the origin directly (bypassing Cloudflare) or
    | when a Cloudflare rule fails. Keep `per_minute` comfortably above the Cloudflare threshold so Cloudflare always
    | trips first. Requests are throttled per client IP, so this is only trustworthy when the real client IP is
    | resolved correctly (see the proxy / trusted-proxies configuration).
    |
    */

    'rate_limiting' => [
        'per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Usage Tracking
    |--------------------------------------------------------------------------
    |
    | The open v0 API is high volume, so usage is tracked as aggregate counters rather than one row per request. A
    | terminable middleware increments per-minute Redis counters off the response hot path, and a scheduled job rolls
    | those counters up into the compact `api_usage_metrics` / `api_usage_clients` tables that the admin dashboard
    | reads. The Redis keys live on a dedicated connection so cache flushes never wipe in-flight counters.
    |
    */

    'usage' => [
        'enabled' => (bool) env('API_USAGE_TRACKING_ENABLED', true),

        // The Redis connection (see config/database.php) that holds the in-flight counters before they are rolled up.
        'connection' => env('API_USAGE_REDIS_CONNECTION', 'api-usage'),

        // The number of heaviest client IPs to persist per bucket. Everything below this is discarded at rollup time.
        'top_clients' => (int) env('API_USAGE_TOP_CLIENTS', 50),

        // Backstop expiry (seconds) on each Redis bucket so counters can never grow unbounded if the rollup stalls.
        'bucket_ttl' => (int) env('API_USAGE_BUCKET_TTL', 7200),

        'retention' => [
            // How long fine-grained per-minute rows are kept before pruning.
            'minute_days' => (int) env('API_USAGE_RETENTION_MINUTE_DAYS', 7),

            // How long coarse daily rollup rows are kept before pruning.
            'day_days' => (int) env('API_USAGE_RETENTION_DAY_DAYS', 365),
        ],
    ],

];
