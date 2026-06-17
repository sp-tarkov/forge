<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds RFC 8594 `Sunset` and RFC 9745 `Deprecation` headers to the legacy Sanctum PAT endpoints. Consumers that
 * honour these headers (Postman, Bruno, well-behaved API client libraries) will surface the deprecation to their
 * users in advance of the actual removal. See Phase 4 of ADR 0001.
 */
final class AnnounceSanctumDeprecation
{
    /**
     * Date the endpoint was marked deprecated. Stays fixed once shipped.
     */
    private const string DEPRECATION_DATE = 'Thu, 28 May 2026 00:00:00 GMT';

    /**
     * Date after which the endpoint may stop responding. Six months from deprecation per ADR 0001.
     */
    private const string SUNSET_DATE = 'Sun, 29 Nov 2026 00:00:00 GMT';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', self::DEPRECATION_DATE);
        $response->headers->set('Sunset', self::SUNSET_DATE);
        $response->headers->set('Link', '<https://forge.sp-tarkov.com/docs/oauth>; rel="successor-version", <https://forge.sp-tarkov.com/docs/api-tokens>; rel="deprecation"');

        return $response;
    }
}
