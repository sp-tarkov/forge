<?php

declare(strict_types=1);

return [
    // Enable/disable Akismet spam detection
    'enabled' => env('AKISMET_ENABLED', false),

    // Your Akismet API key
    'api_key' => env('AKISMET_KEY', ''),

    // Your site URL
    'blog_url' => env('AKISMET_SITE_URL', env('APP_URL')),

    // Auto-moderation settings
    'auto_moderate' => env('AKISMET_AUTO_MODERATE', false),
    'confidence_threshold' => env('AKISMET_CONFIDENCE_THRESHOLD', 0.8),
    'auto_delete_threshold' => env('AKISMET_AUTO_DELETE_THRESHOLD', 0.95),

    // How long to keep spam comments in quarantine (days)
    'quarantine_days' => env('SPAM_QUARANTINE_DAYS', 30),
];
