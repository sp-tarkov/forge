<?php

declare(strict_types=1);

use App\Http\Controllers\ModController;
use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\UserController;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Listing;
use App\Models\Mod;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.banned'])->group(function (): void {

    Route::get('/', Homepage::class)
        ->name('home');

    Route::get('/mods', Listing::class)
        ->can('viewAny', Mod::class)
        ->name('mods');

    // Socialite OAuth Login
    Route::controller(SocialiteController::class)->group(function (): void {
        Route::get('/login/{provider}/redirect', 'redirect')
            ->name('login.socialite');
        Route::get('/login/{provider}/callback', 'callback');
    });

    Route::controller(ModController::class)->group(function (): void {
        Route::get('/mod/{mod}/{slug}', 'show')->where(['mod' => '[0-9]+'])->name('mod.show');
    });

    // Download Link
    Route::controller(ModVersionController::class)->group(function (): void {
        Route::get('/mod/download/{mod}/{slug}/{version}', 'show')
            ->where(['mod' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
            ->name('mod.version.download');
    });

    Route::controller(UserController::class)->group(function (): void {
        Route::get('/user/{user}/{username}', 'show')->where(['user' => '[0-9]+'])->name('user.show');
    });

    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function (): void {
        Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    });

});
