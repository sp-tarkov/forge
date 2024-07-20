<?php

use App\Http\Controllers\ModController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.banned'])->group(function () {

    Route::get('/', function () {
        return view('home');
    })->name('home');

    Route::controller(ModController::class)->group(function () {
        Route::get('/mods', 'index')->name('mods');
        Route::get('/mod/{mod}/{slug}', 'show')->where(['mod' => '[0-9]+'])->name('mod.show');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/user/{user}/{username}', 'show')->where(['user' => '[0-9]+'])->name('user.show');
    });

    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');
    });

});
