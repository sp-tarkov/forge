<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\V0\PublicViewpoint;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins every open v0 API request to the public (guest) viewpoint before the controller builds any query, so listings
 * and detail endpoints return the same published-only data for every caller: anonymous, author, moderator, or admin.
 * The open API has no per-user output, and forcing the viewpoint at the model layer makes that an explicit guarantee
 * rather than an accident of the API happening to run without a session. The website is unaffected; only requests
 * routed through this middleware (the `api` group) force the viewpoint.
 */
final class ForcePublicViewpoint
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        PublicViewpoint::force($request);

        return $next($request);
    }
}
