<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiUsage\ApiUsageRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records usage of the open v0 API as aggregate counters.
 *
 * Registered globally (and gated to `api/v0/*`) so it sees every request including 404s for unmatched paths and 429s
 * raised by the throttle middleware. The actual counter-writing happens in `terminate()`, which runs after the
 * response has been sent to the client, so tracking adds nothing to the response the caller waits on.
 */
final readonly class RecordApiUsage
{
    /**
     * The sentinel route name recorded for requests that matched no registered route.
     */
    public const string UNMATCHED_ROUTE = 'api.v0.unmatched';

    /**
     * The request attribute used to carry the high-resolution start time from handle() to terminate().
     */
    private const string STARTED_AT = 'api_usage_started_at';

    public function __construct(private ApiUsageRecorder $recorder) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tracks($request)) {
            $request->attributes->set(self::STARTED_AT, hrtime(true));
        }

        return $next($request);
    }

    /**
     * Record the completed request once the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->tracks($request) || ! config()->boolean('api.usage.enabled')) {
            return;
        }

        $startedAt = $request->attributes->get(self::STARTED_AT);
        $latencyMs = is_int($startedAt) ? (hrtime(true) - $startedAt) / 1_000_000 : 0.0;
        $routeName = $request->route()?->getName();

        $this->recorder->record(
            $routeName ?? self::UNMATCHED_ROUTE,
            $request->method(),
            $response->getStatusCode(),
            $latencyMs,
            $request->ip(),
            $routeName === null ? $request->path() : null,
        );
    }

    /**
     * Whether this request targets the tracked v0 API surface.
     */
    private function tracks(Request $request): bool
    {
        return $request->is('api/v0/*');
    }
}
