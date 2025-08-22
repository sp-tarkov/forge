<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Visitor Record Retention
    |--------------------------------------------------------------------------
    |
    | The number of months to retain visitor tracking records before cleanup.
    | Older records will be automatically deleted by the cleanup job.
    |
    */
    'retention_months' => env('TRACKING_RETENTION_MONTHS', 36),
];
