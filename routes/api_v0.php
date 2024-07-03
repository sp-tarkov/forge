<?php

use App\Http\Controllers\Api\V0\ModController;
use App\Http\Controllers\Api\V0\UsersController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::apiResource('users', UsersController::class);
    Route::apiResource('mods', ModController::class);
});
