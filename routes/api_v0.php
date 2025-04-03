<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\AuthController;
use App\Http\Controllers\Api\V0\PingController;
use App\Http\Controllers\Api\V0\VerificationController;
use Illuminate\Support\Facades\Route;

// Simple ping endpoint to check API health
Route::get('/ping', PingController::class)->name('api.v0.ping');

// Authentication
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v0.auth.login');
Route::post('/auth/register', [AuthController::class, 'register'])->name('api.v0.auth.register');

// Email Verification Handling
Route::get('/auth/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('api.v0.auth.verify');
Route::post('/auth/email/resend', [VerificationController::class, 'resend'])
    ->middleware('throttle:3,60')
    ->name('api.v0.auth.resend');

// Authenticated (Requires Sanctum Token)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/user', [AuthController::class, 'user'])->name('api.v0.auth.user');

    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v0.auth.logout');
    Route::post('/auth/logout/all', [AuthController::class, 'logoutAll'])->name('api.v0.auth.logout-all');
});
