<?php

declare(strict_types=1);

use App\Http\Controllers\AddonVersionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Chat\StartConversationController;
use App\Http\Controllers\ChatSubscriptionController;
use App\Http\Controllers\CommentSubscriptionController;
use App\Http\Controllers\FileRedirectController;
use App\Http\Controllers\ModRssFeedController;
use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\SocialiteController;
use App\Models\Mod;
use App\Models\Report;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.banned')->group(function (): void {

    Route::livewire('/', 'pages::homepage')
        ->name('home');

    Route::livewire('/mods', 'pages::mod.index')
        ->can('viewAny', Mod::class)
        ->name('mods');

    Route::get('/mods/rss', [ModRssFeedController::class, 'index'])
        ->can('viewAny', Mod::class)
        ->name('mods.rss');

    Route::get('/files/file/{hubId}-{slug}', [FileRedirectController::class, 'redirect'])
        ->where(['hubId' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('files.redirect');

    Route::livewire('/mod/{modId}/{slug}', 'pages::mod.show')
        ->where(['modId' => '[0-9]+', 'slug' => '(?!edit)[a-z0-9-]+'])
        ->name('mod.show');

    Route::get('/mod/download/{mod}/{slug}/{version}', [ModVersionController::class, 'show'])
        ->where(['mod' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('mod.version.download');

    Route::livewire('/addon/{addonId}/{slug}', 'pages::addon.show')
        ->where(['addonId' => '[0-9]+', 'slug' => '(?!edit)[a-z0-9-]+'])
        ->name('addon.show');

    Route::get('/addon/download/{addon}/{slug}/{version}', [AddonVersionController::class, 'show'])
        ->where(['addon' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
        ->name('addon.version.download');

    Route::livewire('/user/{userId}/{slug}', 'pages::user.show')
        ->where(['userId' => '[0-9]+'])
        ->name('user.show');

    Route::livewire('/user-banned', 'pages::user.banned')
        ->name('user.banned');

    // Socialite OAuth Login
    Route::controller(SocialiteController::class)
        ->middleware('throttle:10,1')
        ->group(function (): void {
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
        ->middleware('signed')
        ->name('comment.unsubscribe');

    // Chat unsubscribe route (no auth required for email links)
    Route::get('/chat/unsubscribe/{user}/{conversation}', [ChatSubscriptionController::class, 'unsubscribe'])
        ->middleware('signed')
        ->name('chat.unsubscribe');

    // Authenticated routes
    Route::middleware(['auth', AuthenticateSession::class, 'verified'])->group(function (): void {

        // Authenticated and verified routes
        Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('/mods/recently-updated', 'pages::mod.recently-updated')
            ->can('viewAny', Mod::class)
            ->name('mods.recently-updated');

        Route::livewire('/mods/recently-created', 'pages::mod.recently-created')
            ->can('viewAny', Mod::class)
            ->name('mods.recently-created');

        // Profile routes
        Route::get('/user/profile', fn (): Factory|View => view('profile.show'))->name('profile.show');
        Route::get('/user/api-tokens', fn (): Factory|View => view('api.index'))->name('api-tokens.index');

        Route::livewire('/mod/guidelines', 'pages::mod.guidelines-acknowledgment')
            ->name('mod.guidelines');

        Route::livewire('/mod/create', 'pages::mod.create')
            ->name('mod.create');

        Route::livewire('/mod/{modId}/edit', 'pages::mod.edit')
            ->where(['modId' => '[0-9]+'])
            ->name('mod.edit');

        Route::livewire('/mod/{mod}/version/create', 'pages::mod-version.create')
            ->where(['mod' => '[0-9]+'])
            ->name('mod.version.create');

        Route::livewire('/mod/{mod}/version/{modVersion}/edit', 'pages::mod-version.edit')
            ->where(['mod' => '[0-9]+', 'modVersion' => '[0-9]+'])
            ->name('mod.version.edit');

        Route::livewire('/addon/guidelines/{mod}', 'pages::addon.guidelines-acknowledgment')
            ->where(['mod' => '[0-9]+'])
            ->name('addon.guidelines');

        Route::livewire('/addon/create/{mod}', 'pages::addon.create')
            ->where(['mod' => '[0-9]+'])
            ->name('addon.create');

        Route::livewire('/addon/{addonId}/edit', 'pages::addon.edit')
            ->where(['addonId' => '[0-9]+'])
            ->name('addon.edit');

        Route::livewire('/addon/{addon}/version/create', 'pages::addon-version.create')
            ->where(['addon' => '[0-9]+'])
            ->name('addon.version.create');

        Route::livewire('/addon/{addon}/version/{addonVersion}/edit', 'pages::addon-version.edit')
            ->where(['addon' => '[0-9]+', 'addonVersion' => '[0-9]+'])
            ->name('addon.version.edit');

        Route::livewire('/chat/{conversationHash?}', 'pages::chat')
            ->where(['conversationHash' => '[a-zA-Z0-9]+'])
            ->name('chat');

        Route::get('/chat/start/{user}', StartConversationController::class)
            ->name('chat.start');

        Route::livewire('/report-centre', 'pages::admin.report-centre')
            ->can('viewAny', Report::class)
            ->name('report-centre');

        Route::livewire('/moderation-actions', 'pages::admin.moderation-actions')
            ->can('viewAny', Report::class)
            ->name('moderation-actions');

        // Authenticated, verified, administrator routes
        Route::middleware('can:admin')->group(function (): void {
            Route::livewire('/admin/visitor-analytics', 'pages::admin.visitor-analytics')->name('admin.visitor-analytics');
            Route::livewire('/admin/user-management', 'pages::admin.user-management')->name('admin.user-management');
            Route::livewire('/admin/role-management', 'pages::admin.role-management')->name('admin.role-management');
            Route::livewire('/admin/spt-versions', 'pages::admin.spt-version-management')->name('admin.spt-versions');
        });
    });

    // Routes for static content
    Route::get('/contact', fn (): Factory|View => view('static.contact'))->name('static.contact');
    Route::get('/dmca', fn (): Factory|View => view('static.dmca'))->name('static.dmca');
    Route::get('/community-standards', fn (): Factory|View => view('static.community-standards'))->name('static.community-standards');
    Route::get('/content-guidelines', fn (): Factory|View => view('static.content-guidelines'))->name('static.content-guidelines');
    Route::get('/installer', fn (): Factory|View => view('static.installer'))->name('static.installer');
    Route::get('/privacy-policy', fn (): Factory|View => view('static.privacy'))->name('static.privacy');
    Route::get('/terms-of-service', fn (): Factory|View => view('static.tos'))->name('static.terms');
});
