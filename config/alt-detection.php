<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Alt-Detection Queue
    |--------------------------------------------------------------------------
    |
    | The queue name used for alt-detection investigation jobs. Must match the
    | Horizon supervisor (supervisor-alt-detection) and the local queue worker
    | so these long-running admin analyses run on their own dedicated queue.
    |
    */

    'queue' => env('ALT_DETECTION_QUEUE', 'alt-detection'),

];
