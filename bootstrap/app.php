<?php

declare(strict_types=1);

use App\Exceptions\Api\V0\Handler as ApiV0ExceptionHandler;
use App\Exceptions\Api\V0\InvalidQueryException;
use App\Http\Middleware\ForcePublicViewpoint;
use App\Http\Middleware\RecordApiUsage;
use App\Http\Middleware\RejectMalformedUtf8;
use App\Http\Middleware\SanitizeBroadcastSocketId;
use App\Http\Middleware\SetApiCacheControl;
use App\Http\Middleware\TrackVisitorPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\TooManyCallsException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Mchev\Banhammer\Middleware\AuthBanned;
use Mchev\Banhammer\Middleware\IPBanned;
use Spatie\Honeypot\ProtectAgainstSpam;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v0.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v0',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            RejectMalformedUtf8::class,
            SanitizeBroadcastSocketId::class,
        ]);

        // Track v0 API usage as aggregate counters. Registered globally (it self-gates to `api/v0/*`) so it captures
        // 404s on unmatched paths and 429s from the throttle middleware, and records in terminate() off the hot path.
        $middleware->append(RecordApiUsage::class);

        // Mark cacheable v0 API reads as publicly cacheable so a CDN and browsers can absorb repeat traffic. Self-gates
        // to `api/v0/*` GET 200 responses for guests.
        $middleware->append(SetApiCacheControl::class);

        // Pin every open v0 API request to the public (guest) viewpoint so listings and detail endpoints return the
        // same published-only data for every caller, including authenticated moderators and admins. Applied to the
        // `api` group (which all api/v0 routes share) so it runs before any controller builds a query.
        $middleware->api(prepend: [ForcePublicViewpoint::class]);

        $middleware->append(IPBanned::class);

        // Use Redis-backed rate limiting (skip in tests where Redis may not be available).
        if (! app()->runningUnitTests()) {
            $middleware->throttleWithRedis();
        }

        // Register middleware aliases
        $middleware->alias([
            'auth.banned' => AuthBanned::class,
        ]);

        // Trust only the local Nginx reverse proxy (loopback). Nginx restores the real client IP from Cloudflare's
        // CF-Connecting-IP header before forwarding, so the app reads the true client without trusting arbitrary
        // X-Forwarded-For values sent on direct-to-origin requests.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Protect against spam on the web middleware group, and record visitor presence for the footer "users online"
        // count. Presence is appended to the web group (after the session has started) so it can identify guests; the
        // actual Redis write is deferred until after the response, so it never delays the request.
        $middleware->web(append: [
            ProtectAgainstSpam::class,
            TrackVisitorPresence::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Don't report Livewire payload guard exceptions. These are security
        // limits working as intended (typically triggered by bots/abuse).
        $exceptions->dontReport(TooManyCallsException::class);

        // Invalid API query parameters are user errors that already render a
        // 400 response; they don't represent a server-side bug.
        $exceptions->dontReport(InvalidQueryException::class);

        // Livewire method-not-found errors on the update endpoint are
        // overwhelmingly automated SQLi/XSS probes targeting wire methods.
        $exceptions->dontReport(MethodNotFoundException::class);

        // Attempts to write a #[Locked] Livewire property come from bots replaying stale or malformed update payloads.
        // The locked-property guard is working as intended, so this is not a server-side bug.
        $exceptions->dontReport(CannotUpdateLockedPropertyException::class);

        // Register the custom exception handler for the API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/v0/*') || $request->expectsJson()) {
                return (new ApiV0ExceptionHandler)->render($e);
            }
        });
    })
    ->create();
