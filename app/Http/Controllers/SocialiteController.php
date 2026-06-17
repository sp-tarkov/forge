<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialiteService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class SocialiteController extends Controller
{
    /**
     * The providers that are supported.
     *
     * @var array<int, string>
     */
    private array $providers = ['discord'];

    public function __construct(private readonly SocialiteService $socialiteService) {}

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return to_route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        /** @var AbstractProvider $socialiteProvider */
        $socialiteProvider = Socialite::driver($provider);

        return $socialiteProvider->scopes(['identify', 'email'])->redirect();
    }

    /**
     * Obtain the user information from the provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return to_route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Exception) {
            return to_route('login')->withErrors('Unable to login using '.$provider.'. Please try again.');
        }

        $user = $this->socialiteService->findOrCreateUser($provider, $providerUser);
        if (! $user instanceof User) {
            return to_route('login')
                ->withErrors('Unable to retrieve email from Discord. Please ensure your Discord account has a verified email address and you have granted email access permission.');
        }

        Auth::login($user, remember: true);

        // Honour `redirect()->guest()`'s intended-URL session so OAuth flows that started at `/oauth/authorize` (and
        // bounced the unauthenticated user through Discord) land back on the authorization screen with their query
        // params intact. Falls back to the dashboard for plain logins. See ADR 0001.
        return redirect()->intended(route('dashboard'));
    }
}
