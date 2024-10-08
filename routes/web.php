<?php

use App\Http\Controllers\ModController;
use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.banned'])->group(function () {

    Route::controller(SocialiteController::class)->group(function () {
        Route::get('/login/{provider}/redirect', 'redirect')->name('login.socialite');
        Route::get('/login/{provider}/callback', 'callback');
    });

    Route::get('/', function () {
        return view('home');
    })->name('home');

    Route::controller(ModController::class)->group(function () {
        Route::get('/mods', 'index')->name('mods');
        Route::get('/mod/{mod}/{slug}', 'show')->where(['mod' => '[0-9]+'])->name('mod.show');
    });

    // Download Link
    Route::controller(ModVersionController::class)->group(function () {
        Route::get('/mod/download/{mod}/{slug}/{version}', 'show')
            ->where(['mod' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
            ->name('mod.version.download');
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
