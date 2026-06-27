<?php

declare(strict_types=1);

namespace App\Support\Api\V0;

use Illuminate\Http\Request;

/**
 * Pins model visibility to the public (guest) viewpoint for the lifetime of a single open v0 API request. When forced,
 * the auth-aware global scopes (PublishedScope, PublishedSptVersionScope) resolve published-only results for every
 * caller, including moderators and admins, so an open endpoint returns identical data regardless of who is
 * authenticated. The flag is stored on the request's attribute bag rather than in a static property or singleton, so it
 * is inherently request-scoped: it cannot leak across requests under Octane, and a fresh request (web, queue, console)
 * defaults to the website's normal per-user visibility.
 */
final class PublicViewpoint
{
    /**
     * The request attribute key that marks the current request as pinned to the public viewpoint.
     */
    private const string ATTRIBUTE = 'forcePublicViewpoint';

    /**
     * Pin the given request to the public viewpoint.
     */
    public static function force(Request $request): void
    {
        $request->attributes->set(self::ATTRIBUTE, true);
    }

    /**
     * Determine whether the current request is pinned to the public viewpoint.
     */
    public static function isForced(): bool
    {
        return request()->attributes->getBoolean(self::ATTRIBUTE);
    }
}
