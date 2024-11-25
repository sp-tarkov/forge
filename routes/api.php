<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::delete('/logout', [AuthController::class, 'logout']);
    Route::delete('/logout/all', [AuthController::class, 'logoutAll']);
});
