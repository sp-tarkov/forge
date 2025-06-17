<?php

declare(strict_types=1);

use App\Exceptions\Api\V0\Handler as ApiV0ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Mchev\Banhammer\Middleware\IPBanned;
use Spatie\Honeypot\ProtectAgainstSpam;
use Spatie\LaravelFlare\Facades\Flare;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v0.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v0',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(IPBanned::class);

        // Protect against spam on the web middleware group.
        $middleware->web(append: [
            ProtectAgainstSpam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Register the Flare exception handler.
        Flare::handles($exceptions);

        // Register the custom exception handler for the API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/v0/*') || $request->expectsJson()) {
                return (new ApiV0ExceptionHandler)->render($e, $request);
            }
        });
    })
    ->create();
