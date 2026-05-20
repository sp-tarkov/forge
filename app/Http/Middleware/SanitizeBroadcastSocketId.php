<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SanitizeBroadcastSocketId
{
    /**
     * Drop the X-Socket-ID header when the client sends a non-numeric value
     * (typically the literal string "undefined" when Echo has not finished
     * its handshake). Pusher rejects such values with an exception, which
     * surfaces as a 500 on otherwise-valid broadcast triggers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $socketId = $request->header('X-Socket-ID');

        if (is_string($socketId) && ! preg_match('/^\d+\.\d+$/', $socketId)) {
            $request->headers->remove('X-Socket-ID');
        }

        return $next($request);
    }
}
