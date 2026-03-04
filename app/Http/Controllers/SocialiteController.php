<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SocialiteService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialiteController extends Controller
{
    /**
     * The providers that are supported.
     *
     * @var array<int, string>
     */
    protected array $providers = ['discord'];

    public function __construct(private SocialiteService $socialiteService) {}

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return to_route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        $socialiteProvider = Socialite::driver($provider);

        if (method_exists($socialiteProvider, 'scopes')) {
            return $socialiteProvider->scopes(['identify', 'email'])->redirect();
        }

        return $socialiteProvider->redirect();
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
        if ($user === null) {
            return to_route('login')
                ->withErrors('Unable to retrieve email from Discord. Please ensure your Discord account has a verified email address and you have granted email access permission.');
        }

        Auth::login($user, remember: true);

        return to_route('dashboard');
    }
}
