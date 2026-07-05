<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Online Visitor Window
    |--------------------------------------------------------------------------
    |
    | A visitor counts as "currently online" while their last page activity is within this many seconds. This is the
    | main accuracy knob for the footer counter: a longer window feels stickier, a shorter window is more precise.
    | The visitor tracker's heartbeat poll runs at a third of this window, so tuning this retunes the heartbeat too.
    |
    */

    'online_window' => (int) env('VISITOR_PRESENCE_WINDOW', 180),

    /*
    |--------------------------------------------------------------------------
    | Presence Redis Connection
    |--------------------------------------------------------------------------
    |
    | The Redis connection (see config/database.php) that holds the ephemeral presence sorted sets. The live count is
    | disposable, so it is fine for this to share a connection that may be flushed; normal traffic repopulates it.
    |
    */

    'connection' => env('VISITOR_PRESENCE_REDIS_CONNECTION', 'default'),

];
