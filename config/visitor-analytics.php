<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor Analytics Queue
    |--------------------------------------------------------------------------
    |
    | The queue name used for visitor analytics stats jobs. Must match the
    | Horizon supervisor (supervisor-alt-detection) and the local queue worker
    | so these long-running admin aggregations run on a dedicated queue.
    |
    */

    'queue' => env('VISITOR_ANALYTICS_QUEUE', 'visitor-analytics'),

];
