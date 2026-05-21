<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mod Lists Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for user-curated mod lists, including per-user caps and
    | per-list item caps. The default "Favourites" list is included in the
    | per-user cap.
    |
    */

    'max_lists_per_user' => (int) env('MOD_LISTS_MAX_PER_USER', 50),

    'max_items_per_list' => (int) env('MOD_LISTS_MAX_ITEMS', 250),

    'validation' => [
        'title_max' => 120,
        'description_max' => 5000,
        'note_max' => 280,
    ],

    'rate_limiting' => [
        'create_max_attempts' => (int) env('MOD_LISTS_CREATE_MAX_ATTEMPTS', 15),
        'create_duration_seconds' => (int) env('MOD_LISTS_CREATE_DURATION', 60),
    ],

    'favourites' => [
        'title' => 'Favourites',
        'slug' => 'favourites',
    ],
];
