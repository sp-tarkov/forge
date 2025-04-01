<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V0\ModController;
use App\Http\Controllers\Api\V0\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('users', UsersController::class);
    Route::apiResource('mods', ModController::class);
});
