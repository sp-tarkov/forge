<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SanitizeBroadcastSocketId
{
    /**
     * Guard Pusher against malformed socket IDs, which it rejects with an exception that surfaces as a 500.
     *
     * Two vectors are covered. The X-Socket-ID header carries a non-numeric value (typically the literal string
     * "undefined" before Echo finishes its handshake) and is simply dropped. The broadcasting auth endpoint also reads
     * socket_id from the request body and forwards it straight to Pusher; the header drop does not cover that, so a
     * body socket_id that is not the canonical <number>.<number> form (e.g. an injection probe) is rejected as
     * forbidden. Legitimate clients always send a well-formed id, so this never affects real traffic.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $socketId = $request->header('X-Socket-ID');

        if (is_string($socketId) && ! preg_match('/^\d+\.\d+$/', $socketId)) {
            $request->headers->remove('X-Socket-ID');
        }

        $socketIdInput = $request->input('socket_id');

        if ($socketIdInput !== null && (! is_string($socketIdInput) || ! preg_match('/^\d+\.\d+$/', $socketIdInput))) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
