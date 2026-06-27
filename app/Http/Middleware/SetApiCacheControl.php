<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks successful, anonymous v0 API reads as publicly cacheable so a CDN (Cloudflare) and browsers can serve repeat
 * requests without reaching the origin. This is the main lever for absorbing a traffic spike on a single server. Only
 * GET requests that return 200 to a guest are marked public: authenticated responses vary per user (see PublishedScope)
 * and must never be shared by a cache. The response body is unchanged; a cached copy can be up to max-age seconds stale.
 *
 * Registered globally and gated to `api/v0/*` so it never touches web responses. Cloudflare still needs a Cache Rule to
 * store JSON at the edge; this middleware supplies the origin headers that such a rule honours, and enables browser and
 * intermediary caching on its own.
 */
final class SetApiCacheControl
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isCacheableRead($request, $response)) {
            return $response;
        }

        $maxAge = $this->maxAgeFor($request);

        if ($maxAge > 0) {
            $response->setPublic();
            $response->setMaxAge($maxAge);
        }

        return $response;
    }

    /**
     * Determine whether the response is a public, anonymous read that is safe to share from a cache.
     */
    private function isCacheableRead(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $request->is('api/v0/*')
            && $response->getStatusCode() === Response::HTTP_OK
            && Auth::guest()
            && ! $response->headers->has('Set-Cookie');
    }

    /**
     * Resolve the max-age (seconds) for the matched route, falling back to the configured default. A non-positive value
     * leaves the response uncached. Route names are looked up as literal keys because they contain dots that config dot
     * notation would otherwise treat as nested segments.
     */
    private function maxAgeFor(Request $request): int
    {
        /** @var array<string, mixed> $overrides */
        $overrides = config()->array('api.cache_control.overrides', []);

        $routeName = $request->route()?->getName();

        if ($routeName !== null && isset($overrides[$routeName]) && is_int($overrides[$routeName])) {
            return $overrides[$routeName];
        }

        return config()->integer('api.cache_control.default_max_age', 60);
    }
}
