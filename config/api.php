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

];
