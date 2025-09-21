<?php

declare(strict_types=1);

namespace App\Http\SpamResponders;

use Closure;
use Illuminate\Http\Request;
use Spatie\Honeypot\SpamResponder\SpamResponder;

class AbortResponder implements SpamResponder
{
    public function respond(Request $request, Closure $next): mixed
    {
        return abort(418);
    }
}
