<?php

declare(strict_types=1);

use Shetabit\Visitor\Drivers\JenssegersAgent;
use Shetabit\Visitor\Drivers\UAParser;

return [
    /*
    |--------------------------------------------------------------------------
    | Default User Agent Parser Driver
    |--------------------------------------------------------------------------
    |
    | Specifies which user agent parsing driver to use by default. The driver
    | is responsible for analyzing HTTP User-Agent strings and extracting
    | device type, operating system, browser name, and other visitor metadata.
    |
    | Supported drivers: "jenssegers", "UAParser"
    |
    */
    'default' => 'jenssegers',

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes or request patterns that should be excluded from automatic visitor
    | tracking. This is useful for avoiding tracking of authentication routes,
    | API endpoints, or other requests that don't represent meaningful visits.
    |
    */
    'except' => [],

    /*
    |--------------------------------------------------------------------------
    | Tracking Database Table
    |--------------------------------------------------------------------------
    |
    | The database table name where visitor tracking data should be stored.
    | This should match the table used by your tracking implementation.
    |
    */
    'table_name' => 'tracking_events',

    /*
    |--------------------------------------------------------------------------
    | User Agent Parser Drivers
    |--------------------------------------------------------------------------
    |
    | Available user agent parsing drivers and their corresponding classes.
    | Each driver implements different parsing libraries with varying accuracy
    | and performance characteristics.
    |
    */
    'drivers' => [
        'jenssegers' => JenssegersAgent::class,
        'UAParser' => UAParser::class,
    ],
];
