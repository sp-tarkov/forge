<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::delete('/logout', [AuthController::class, 'logout']);
    Route::delete('/logout/all', [AuthController::class, 'logoutAll']);
});
