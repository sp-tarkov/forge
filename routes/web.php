<?php

declare(strict_types=1);

use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\SocialiteController;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Mod\Create as ModCreate;
use App\Livewire\Page\Mod\Edit as ModEdit;
use App\Livewire\Page\Mod\Index as ModIndex;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Livewire\Page\ModVersion\Create as ModVersionCreate;
use App\Livewire\Page\ModVersion\Edit as ModVersionEdit;
use App\Livewire\Page\User\Show as UserShow;
use App\Models\Mod;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.banned'])->group(function (): void {

    Route::get('/', Homepage::class)
        ->name('home');

    Route::get('/mods', ModIndex::class)
        ->can('viewAny', Mod::class)
        ->name('mods');

    Route::get('/mod/create', ModCreate::class)
        ->name('mod.create');

    Route::get('/mod/{modId}/edit', ModEdit::class)
        ->where(['modId' => '[0-9]+'])
        ->name('mod.edit');

    Route::get('/mod/{modId}/{slug}', ModShow::class)
        ->where(['modId' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('mod.show');

    Route::get('/mod/{mod}/version/create', ModVersionCreate::class)
        ->where(['mod' => '[0-9]+'])
        ->name('mod.version.create');

    Route::get('/mod/{mod}/version/{modVersion}/edit', ModVersionEdit::class)
        ->where(['mod' => '[0-9]+', 'modVersion' => '[0-9]+'])
        ->name('mod.version.edit');

    Route::get('/mod/download/{mod}/{slug}/{version}', [ModVersionController::class, 'show'])
        ->where(['mod' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('mod.version.download');

    Route::get('/user/{userId}/{slug}', UserShow::class)
        ->where(['userId' => '[0-9]+'])
        ->name('user.show');

    // Socialite OAuth Login
    Route::controller(SocialiteController::class)->group(function (): void {
        Route::get('/login/{provider}/redirect', 'redirect')
            ->name('login.socialite');
        Route::get('/login/{provider}/callback', 'callback');
    });

    // Jetstream/Profile Routes
    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function (): void {
        Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    });
});
