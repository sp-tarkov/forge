<?php

namespace App\Http\Controllers;

use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * The providers that are supported.
     */
    protected array $providers = ['discord'];

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return redirect()->route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        return Socialite::driver('discord')
            ->scopes([
                'identify',
                'email',
            ])
            ->redirect();
    }

    /**
     * Obtain the user information from the provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return redirect()->route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors('Unable to login using '.$provider.'. Please try again.');
        }

        $user = $this->findOrCreateUser($provider, $providerUser);

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }

    protected function findOrCreateUser(string $provider, ProviderUser $providerUser): User
    {
        $oauthConnection = OAuthConnection::whereProvider($provider)
            ->whereProviderId($providerUser->getId())
            ->first();

        if ($oauthConnection) {
            $oauthConnection->update([
                'token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken ?? null,
            ]);

            return $oauthConnection->user;
        }

        // The user has not connected their account with this OAuth provider before, so a new connection needs to be
        // established. Check if the user has an account with the same email address that's passed in from the provider.
        // If one exists, connect that account. Otherwise, create a new one.

        return DB::transaction(function () use ($providerUser, $provider) {
            $user = User::firstOrCreate(['email' => $providerUser->getEmail()], [
                'name' => $providerUser->getName() ?? $providerUser->getNickname(),
                'password' => null,
            ]);
            $user->oAuthConnections()->create([
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken ?? null,
            ]);

            return $user;
        });
    }
}
