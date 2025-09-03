<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

/*
 * A private broadcast "presents" channel that we've allowed unauthorized users to join by assigning a temporary Guest
 * model as their user state. Guest users are unique based on their session ID.
 */
Broadcast::channel('visitors', function ($user) {
    $anonId = Str::of($user->id)
        ->prepend(config('app.key'))
        ->hash('sha256')
        ->take(12)
        ->value();

    return [
        'id' => $anonId,
        'type' => isset($user->is_guest) && $user->is_guest ? 'guest' : 'authenticated',
    ];
});
