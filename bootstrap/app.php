<?php

declare(strict_types=1);

use App\Exceptions\Api\V0\Handler as ApiV0ExceptionHandler;
use App\Exceptions\Api\V0\InvalidQueryException;
use App\Http\Middleware\AnnounceSanctumDeprecation;
use App\Http\Middleware\EnforceApiScope;
use App\Http\Middleware\RejectMalformedUtf8;
use App\Http\Middleware\SanitizeBroadcastSocketId;
use App\Http\Middleware\UpdatePassportTokenLastUsed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
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

        $middleware->append(IPBanned::class);

        // Use Redis-backed rate limiting (skip in tests where Redis may not be available).
        if (! app()->runningUnitTests()) {
            $middleware->throttleWithRedis();
        }

        // Register middleware aliases
        $middleware->alias([
            'auth.banned' => AuthBanned::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'api.scope' => EnforceApiScope::class,
            'api.last_used' => UpdatePassportTokenLastUsed::class,
            'api.sanctum_deprecated' => AnnounceSanctumDeprecation::class,
        ]);

        // Trust proxies to get real client IP addresses
        $middleware->trustProxies(at: '*');

        // Protect against spam on the web middleware group.
        $middleware->web(append: [
            ProtectAgainstSpam::class,
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
