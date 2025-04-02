<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\AuthController;
use App\Http\Controllers\Api\V0\PingController;
use Illuminate\Support\Facades\Route;

// Simple ping endpoint to check API health
Route::get('/ping', PingController::class)->name('api.v0.ping');

// Authentication
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v0.auth.login');

// Authenticated (Requires Sanctum Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v0.auth.logout');
    Route::post('/auth/logout/all', [AuthController::class, 'logoutAll'])->name('api.v0.auth.logout-all');
});
