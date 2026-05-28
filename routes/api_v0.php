<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\AddonController;
use App\Http\Controllers\Api\V0\AddonDependencyController;
use App\Http\Controllers\Api\V0\AddonVersionController;
use App\Http\Controllers\Api\V0\AuthController;
use App\Http\Controllers\Api\V0\ModCategoryController;
use App\Http\Controllers\Api\V0\ModController;
use App\Http\Controllers\Api\V0\ModDependencyController;
use App\Http\Controllers\Api\V0\ModUpdateController;
use App\Http\Controllers\Api\V0\ModVersionController;
use App\Http\Controllers\Api\V0\PingController;
use App\Http\Controllers\Api\V0\SptVersionController;
use Illuminate\Support\Facades\Route;

// Simple ping endpoint to check API health
Route::get('/ping', PingController::class)->name('api.v0.ping');

// Authentication
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.v0.auth.login');
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1')
    ->name('api.v0.auth.register');
Route::post('/auth/email/resend', [AuthController::class, 'resend'])
    ->middleware('throttle:3,60')
    ->name('api.v0.auth.resend');

/*
 * Authenticated routes accept either a Passport OAuth bearer token (api guard) or, during the deprecation window, a
 * legacy Sanctum personal access token (sanctum guard). The `api.scope` middleware enforces the right Passport scope
 * for the endpoint and, for Sanctum-authenticated requests, requires the equivalent legacy ability. See ADR 0001.
 */
Route::middleware(['throttle:api', 'auth:sanctum,api'])->group(function (): void {
    // Auth & profile
    Route::get('/auth/user', [AuthController::class, 'user'])
        ->middleware('api.scope:profile:read')
        ->name('api.v0.auth.user');
    Route::get('/auth/abilities', [AuthController::class, 'abilities'])
        ->middleware('api.scope:profile:read')
        ->name('api.v0.auth.abilities');

    // Any authenticated caller can revoke their own token; no scope required beyond proof of authentication.
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v0.auth.logout');
    Route::post('/auth/logout/all', [AuthController::class, 'logoutAll'])->name('api.v0.auth.logout-all');

    // Mods
    Route::middleware('api.scope:mods:read')->group(function (): void {
        Route::get('/mods', [ModController::class, 'index'])->name('api.v0.mods');
        Route::get('/mod/{modId}', [ModController::class, 'show'])->where('modId', '[0-9]+')->name('api.v0.mods.show');
        Route::get('/mod/{modId}/versions', [ModVersionController::class, 'index'])->where('modId', '[0-9]+')->name('api.v0.mod.versions');
        Route::get('/mods/dependencies', [ModDependencyController::class, 'resolve'])->name('api.v0.mods.dependencies');
        Route::get('/mods/updates', [ModUpdateController::class, 'check'])->name('api.v0.mods.updates');
    });

    // Addons
    Route::middleware('api.scope:addons:read')->group(function (): void {
        Route::get('/addons', [AddonController::class, 'index'])->name('api.v0.addons');
        Route::get('/addon/{addonId}', [AddonController::class, 'show'])->where('addonId', '[0-9]+')->name('api.v0.addons.show');
        Route::get('/addon/{addonId}/versions', [AddonVersionController::class, 'index'])->where('addonId', '[0-9]+')->name('api.v0.addons.versions');
        Route::get('/addons/dependencies', [AddonDependencyController::class, 'resolve'])->name('api.v0.addons.dependencies');
    });

    // Mod Categories
    Route::middleware('api.scope:categories:read')->group(function (): void {
        Route::get('/mod-categories', [ModCategoryController::class, 'index'])->name('api.v0.mod-categories');
        Route::get('/mod-categories/{identifier}', [ModCategoryController::class, 'show'])->name('api.v0.mod-categories.show');
    });

    // SPT Versions
    Route::middleware('api.scope:spt:read')->group(function (): void {
        Route::get('/spt/versions', [SptVersionController::class, 'index'])->name('api.v0.spt.versions');
    });
});
