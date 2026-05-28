<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

final class PassportServiceProvider extends ServiceProvider
{
    /**
     * Token scopes available to OAuth clients. Each value is the human-readable description shown on the consent
     * screen. Keep these in sync with `routes/api_v0.php` middleware and ADR 0001.
     *
     * @var array<string, string>
     */
    private const array SCOPES = [
        'profile:read' => 'Read your basic profile information (name, email, avatar, role).',
        'mods:read' => 'Browse mods, mod versions, dependencies, and update feeds.',
        'addons:read' => 'Browse addons, addon versions, and dependencies.',
        'categories:read' => 'Read mod categories.',
        'spt:read' => 'Read available SPT versions.',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Passport::tokensCan(self::SCOPES);

        // No default scope. Clients must request what they need explicitly so consent screens never show
        // "all permissions granted" by accident.
        Passport::setDefaultScope([]);

        // OAuth 2.1 posture: short-lived access tokens, long-lived sliding refresh tokens. See ADR 0001.
        Passport::tokensExpireIn(Date::now()->addHour());
        Passport::refreshTokensExpireIn(Date::now()->addDays(90));

        // Personal access tokens are only used by the legacy Sanctum-equivalent flow which we are deprecating; a
        // year is fine until those endpoints are removed.
        Passport::personalAccessTokensExpireIn(Date::now()->addYear());

        // Replace the default consent screen with our Flux-styled view (resources/views/oauth/authorize.blade.php).
        Passport::authorizationView('oauth.authorize');
    }
}
