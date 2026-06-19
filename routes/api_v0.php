<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\AddonController;
use App\Http\Controllers\Api\V0\AddonDependencyController;
use App\Http\Controllers\Api\V0\AddonVersionController;
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

// The v0 API is read-only and open. Requests are rate-limited per IP via the `api` limiter (see AppServiceProvider).
Route::middleware('throttle:api')->group(function (): void {
    // Mods
    Route::get('/mods', [ModController::class, 'index'])->name('api.v0.mods');
    Route::get('/mod/{modId}', [ModController::class, 'show'])->where('modId', '[0-9]+')->name('api.v0.mods.show');
    Route::get('/mod/{modId}/versions', [ModVersionController::class, 'index'])->where('modId', '[0-9]+')->name('api.v0.mod.versions');
    Route::get('/mods/dependencies', [ModDependencyController::class, 'resolve'])->name('api.v0.mods.dependencies');
    Route::get('/mods/updates', [ModUpdateController::class, 'check'])->name('api.v0.mods.updates');

    // Addons
    Route::get('/addons', [AddonController::class, 'index'])->name('api.v0.addons');
    Route::get('/addon/{addonId}', [AddonController::class, 'show'])->where('addonId', '[0-9]+')->name('api.v0.addons.show');
    Route::get('/addon/{addonId}/versions', [AddonVersionController::class, 'index'])->where('addonId', '[0-9]+')->name('api.v0.addons.versions');
    Route::get('/addons/dependencies', [AddonDependencyController::class, 'resolve'])->name('api.v0.addons.dependencies');

    // Mod Categories
    Route::get('/mod-categories', [ModCategoryController::class, 'index'])->name('api.v0.mod-categories');
    Route::get('/mod-categories/{identifier}', [ModCategoryController::class, 'show'])->name('api.v0.mod-categories.show');

    // SPT Versions
    Route::get('/spt/versions', [SptVersionController::class, 'index'])->name('api.v0.spt.versions');
});
