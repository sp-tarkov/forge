<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\VisitorPresenceStore;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Records the current visitor's activity so the footer can show a live "users online" count without WebSockets.
 *
 * The visitor token is derived synchronously (it is already in memory), but the Redis write is handed to `defer()` so
 * it runs after the response has been flushed to the client. The request therefore never waits on presence tracking.
 * Recording is idempotent per visitor, so navigating or reloading only refreshes the timestamp, never inflates the
 * count. The namespaced `defer` import is required so it does not collide with Swoole's global `defer` under Octane.
 */
final readonly class TrackVisitorPresence
{
    public function __construct(private VisitorPresenceStore $store) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $visitor = $this->identify($request);

        if ($visitor !== null) {
            [$token, $isMember] = $visitor;

            defer(fn () => $this->store->record($token, $isMember));
        }

        return $next($request);
    }

    /**
     * Resolve a stable token for the current visitor, or null when one cannot be determined.
     *
     * Authenticated users are keyed by their id so they count once across tabs and devices. Guests are keyed by a hash
     * of their session id (never the raw id) so they count once per session without exposing the identifier.
     *
     * @return array{0: string, 1: bool}|null The token and whether the visitor is an authenticated member.
     */
    private function identify(Request $request): ?array
    {
        $user = $request->user();

        if ($user instanceof User) {
            return ['u:'.$user->id, true];
        }

        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        if ($sessionId === '') {
            return null;
        }

        return ['g:'.mb_substr(hash('sha256', $sessionId.config()->string('app.key')), 0, 16), false];
    }
}
