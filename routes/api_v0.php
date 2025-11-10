<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\AddonController;
use App\Http\Controllers\Api\V0\AddonVersionController;
use App\Http\Controllers\Api\V0\AuthController;
use App\Http\Controllers\Api\V0\ModCategoryController;
use App\Http\Controllers\Api\V0\ModController;
use App\Http\Controllers\Api\V0\ModDependencyController;
use App\Http\Controllers\Api\V0\ModVersionController;
use App\Http\Controllers\Api\V0\PingController;
use App\Http\Controllers\Api\V0\SptVersionController;
use Illuminate\Support\Facades\Route;

// Simple ping endpoint to check API health
Route::get('/ping', PingController::class)->name('api.v0.ping');

// Authentication
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v0.auth.login');
Route::post('/auth/register', [AuthController::class, 'register'])->name('api.v0.auth.register');
Route::post('/auth/email/resend', [AuthController::class, 'resend'])
    ->middleware('throttle:3,60')
    ->name('api.v0.auth.resend');

// Authenticated (Requires Sanctum Token)
Route::middleware('auth:sanctum')->group(function (): void {
    // Auth
    Route::get('/auth/user', [AuthController::class, 'user'])->name('api.v0.auth.user');
    Route::get('/auth/abilities', [AuthController::class, 'abilities'])->name('api.v0.auth.abilities');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v0.auth.logout');
    Route::post('/auth/logout/all', [AuthController::class, 'logoutAll'])->name('api.v0.auth.logout-all');

    // Mods
    Route::get('/mods', [ModController::class, 'index'])->name('api.v0.mods');
    Route::get('/mod/{modId}', [ModController::class, 'show'])->where('modId', '[0-9]+')->name('api.v0.mods.show');
    Route::get('/mod/{modId}/versions', [ModVersionController::class, 'index'])->where('modId', '[0-9]+')->name('api.v0.mod.versions');
    Route::get('/mods/dependencies/tree', [ModDependencyController::class, 'resolve'])->name('api.v0.mods.dependencies.tree');

    // Addons
    Route::get('/addons', [AddonController::class, 'index'])->name('api.v0.addons');
    Route::get('/addon/{addonId}', [AddonController::class, 'show'])->where('addonId', '[0-9]+')->name('api.v0.addons.show');
    Route::get('/addon/{addonId}/versions', [AddonVersionController::class, 'index'])->where('addonId', '[0-9]+')->name('api.v0.addons.versions');

    // Mod Categories
    Route::get('/mod-categories', [ModCategoryController::class, 'index'])->name('api.v0.mod-categories');
    Route::get('/mod-categories/{identifier}', [ModCategoryController::class, 'show'])->name('api.v0.mod-categories.show');

    // SPT Versions
    Route::get('/spt/versions', [SptVersionController::class, 'index'])->name('api.v0.spt.versions');
});
