<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Comment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the comment system,
    | including spam detection, rate limiting, and validation rules.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Spam Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for Akismet spam detection and automatic recheck behavior.
    |
    */
    'spam' => [
        'max_recheck_attempts' => (int) env('COMMENT_SPAM_MAX_RECHECK_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for comment creation rate limiting. Staff and
    | moderators are automatically exempt from these limits.
    |
    */
    'rate_limiting' => [
        'duration_seconds' => (int) env('COMMENT_RATE_LIMIT_DURATION', 30),
        'max_attempts' => (int) env('COMMENT_RATE_LIMIT_ATTEMPTS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comment Validation
    |--------------------------------------------------------------------------
    |
    | Validation rules for comment content length and requirements.
    |
    */
    'validation' => [
        'min_length' => (int) env('COMMENT_MIN_LENGTH', 3),
        'max_length' => (int) env('COMMENT_MAX_LENGTH', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comment Editing
    |--------------------------------------------------------------------------
    |
    | Time limits for comment editing and deletion behavior.
    |
    */
    'editing' => [
        'edit_time_limit_minutes' => (int) env('COMMENT_EDIT_TIME_LIMIT_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting non-English comments and translating them
    | into English. Detection runs locally first; only uncertain or
    | non-English comments are sent to the Anthropic API.
    |
    */
    'translation' => [
        'enabled' => (bool) env('COMMENT_TRANSLATION_ENABLED', false),
        'model' => env('COMMENT_TRANSLATION_MODEL', 'claude-haiku-4-5'),
        'target_language' => 'en',
        'max_tokens' => (int) env('COMMENT_TRANSLATION_MAX_TOKENS', 8192),
        'min_length' => (int) env('COMMENT_TRANSLATION_MIN_LENGTH', 15),
        'batch_size' => (int) env('COMMENT_TRANSLATION_BATCH_SIZE', 100),
        'scan_chunk' => (int) env('COMMENT_TRANSLATION_SCAN_CHUNK', 2500),
    ],
];
