<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\StampAccessTokenDevice;
use App\Support\Passport\AuthCodeRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AuthCodeRepository as BridgeAuthCodeRepository;
use Laravel\Passport\Events\AccessTokenCreated;
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
        // Swap Passport's auth-code repository for our subclass so the `device_name` query parameter survives the
        // consent step and is available when /oauth/token issues the eventual access token. See ADR 0001.
        $this->app->bind(BridgeAuthCodeRepository::class, AuthCodeRepository::class);
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

        // Stamp newly-issued access tokens with the launcher-supplied device label so the Connected Apps page can
        // distinguish "Launcher on Desktop-PC" from "Launcher on Laptop" per session.
        Event::listen(AccessTokenCreated::class, StampAccessTokenDevice::class);

        $this->registerOAuthRateLimiter();
    }

    /**
     * Path-aware rate limiter applied to every Passport route via `config/passport.php`. The high-risk grant
     * endpoints (/oauth/token, /oauth/token/refresh) get a tighter per-IP limit because they accept opaque codes
     * and refresh tokens; the consent flow (/oauth/authorize) gets a generous limit because real users only hit it
     * once per app authorization.
     */
    private function registerOAuthRateLimiter(): void
    {
        RateLimiter::for('oauth', function (Request $request): Limit {
            $key = $request->ip() ?? 'unknown';

            if ($request->is('oauth/token', 'oauth/token/refresh')) {
                return Limit::perMinute(30)->by('oauth-token:'.$key);
            }

            return Limit::perMinute(120)->by('oauth:'.$key);
        });
    }
}
