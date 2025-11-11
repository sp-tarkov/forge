<?php

declare(strict_types=1);

use App\Http\Controllers\AddonVersionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ChatSubscriptionController;
use App\Http\Controllers\CommentSubscriptionController;
use App\Http\Controllers\FileRedirectController;
use App\Http\Controllers\ModRssFeedController;
use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\SocialiteController;
use App\Livewire\Admin\SptVersionManagement;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Admin\VisitorAnalytics;
use App\Livewire\Page\Addon\GuidelinesAcknowledgment as AddonGuidelinesAcknowledgment;
use App\Livewire\Page\Chat;
use App\Livewire\Page\Homepage;
use App\Livewire\Page\Mod\Create as ModCreate;
use App\Livewire\Page\Mod\Edit as ModEdit;
use App\Livewire\Page\Mod\GuidelinesAcknowledgment as ModGuidelinesAcknowledgment;
use App\Livewire\Page\Mod\Index as ModIndex;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Livewire\Page\ModVersion\Create as ModVersionCreate;
use App\Livewire\Page\ModVersion\Edit as ModVersionEdit;
use App\Livewire\Page\User\Show as UserShow;
use App\Models\Mod;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.banned')->group(function (): void {

    Route::get('/', Homepage::class)
        ->name('home');

    Route::get('/mods', ModIndex::class)
        ->can('viewAny', Mod::class)
        ->name('mods');

    Route::get('/mods/rss', [ModRssFeedController::class, 'index'])
        ->can('viewAny', Mod::class)
        ->name('mods.rss');

    Route::get('/files/file/{hubId}-{slug}', [FileRedirectController::class, 'redirect'])
        ->where(['hubId' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('files.redirect');

    Route::get('/mod/{modId}/{slug}', ModShow::class)
        ->where(['modId' => '[0-9]+', 'slug' => '(?!edit)[a-z0-9-]+'])
        ->name('mod.show');

    Route::get('/mod/download/{mod}/{slug}/{version}', [ModVersionController::class, 'show'])
        ->where(['mod' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('mod.version.download');

    Route::get('/addon/{addonId}/{slug}', App\Livewire\Page\Addon\Show::class)
        ->where(['addonId' => '[0-9]+', 'slug' => '(?!edit)[a-z0-9-]+'])
        ->name('addon.show');

    Route::get('/addon/download/{addon}/{slug}/{version}', [AddonVersionController::class, 'show'])
        ->where(['addon' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('addon.version.download');

    Route::get('/user/{userId}/{slug}', UserShow::class)
        ->where(['userId' => '[0-9]+'])
        ->name('user.show');

    // Socialite OAuth Login
    Route::controller(SocialiteController::class)->group(function (): void {
        Route::get('/login/{provider}/redirect', 'redirect')
            ->name('login.socialite');
        Route::get('/login/{provider}/callback', 'callback');
    });

    // Email verification route without auth requirement (Fortify override)
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['web', 'signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Comment unsubscribe route (no auth required for email links)
    Route::get('/comment/unsubscribe/{user}/{commentable_type}/{commentable_id}', [CommentSubscriptionController::class, 'unsubscribe'])
        ->name('comment.unsubscribe');

    // Chat unsubscribe route (no auth required for email links)
    Route::get('/chat/unsubscribe/{user}/{conversation}', [ChatSubscriptionController::class, 'unsubscribe'])
        ->name('chat.unsubscribe');

    // Authenticated routes
    Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function (): void {

        // Authenticated and verified routes
        Route::get('/dashboard', fn (): View|Factory => view('dashboard'))->name('dashboard');

        Route::get('/mod/guidelines', ModGuidelinesAcknowledgment::class)
            ->name('mod.guidelines');

        Route::get('/mod/create', ModCreate::class)
            ->name('mod.create');

        Route::get('/mod/{modId}/edit', ModEdit::class)
            ->where(['modId' => '[0-9]+'])
            ->name('mod.edit');

        Route::get('/mod/{mod}/version/create', ModVersionCreate::class)
            ->where(['mod' => '[0-9]+'])
            ->name('mod.version.create');

        Route::get('/mod/{mod}/version/{modVersion}/edit', ModVersionEdit::class)
            ->where(['mod' => '[0-9]+', 'modVersion' => '[0-9]+'])
            ->name('mod.version.edit');

        Route::get('/addon/guidelines/{mod}', AddonGuidelinesAcknowledgment::class)
            ->where(['mod' => '[0-9]+'])
            ->name('addon.guidelines');

        Route::get('/addon/create/{mod}', App\Livewire\Page\Addon\Create::class)
            ->where(['mod' => '[0-9]+'])
            ->name('addon.create');

        Route::get('/addon/{addonId}/edit', App\Livewire\Page\Addon\Edit::class)
            ->where(['addonId' => '[0-9]+'])
            ->name('addon.edit');

        Route::get('/addon/{addon}/version/create', App\Livewire\Page\AddonVersion\Create::class)
            ->where(['addon' => '[0-9]+'])
            ->name('addon.version.create');

        Route::get('/addon/{addon}/version/{addonVersion}/edit', App\Livewire\Page\AddonVersion\Edit::class)
            ->where(['addon' => '[0-9]+', 'addonVersion' => '[0-9]+'])
            ->name('addon.version.edit');

        Route::get('/chat/{conversationHash?}', Chat::class)
            ->where(['conversationHash' => '[a-zA-Z0-9]+'])
            ->name('chat');

        // Authenticated, verified, administrator routes
        Route::middleware('can:admin')->group(function (): void {
            Route::get('/admin/visitor-analytics', VisitorAnalytics::class)->name('admin.visitor-analytics');
            Route::get('/admin/user-management', UserManagement::class)->name('admin.user-management');
            Route::get('/admin/spt-versions', SptVersionManagement::class)->name('admin.spt-versions');
        });
    });

    // Routes for static content
    Route::view('/contact', 'static.contact')->name('static.contact');
    Route::view('/dmca', 'static.dmca')->name('static.dmca');
    Route::view('/community-standards', 'static.community-standards')->name('static.community-standards');
    Route::view('/content-guidelines', 'static.content-guidelines')->name('static.content-guidelines');
    Route::view('/installer', 'static.installer')->name('static.installer');
    Route::view('/privacy-policy', 'static.privacy')->name('static.privacy');
    Route::view('/terms-of-service', 'static.tos')->name('static.terms');
});
