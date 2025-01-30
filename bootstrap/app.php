<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Mchev\Banhammer\Middleware\IPBanned;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v0')
                ->name('api.v0.')
                ->group(base_path('routes/api_v0.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(IPBanned::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
