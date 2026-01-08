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
];
