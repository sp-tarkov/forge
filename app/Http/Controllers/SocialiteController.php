<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OAuthConnection;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialiteController extends Controller
{
    /**
     * The providers that are supported.
     */
    protected array $providers = ['discord'];

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            return redirect()->route('login')->withErrors(__('Unsupported OAuth provider.'));
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
            return redirect()->route('login')->withErrors(__('Unsupported OAuth provider.'));
        }

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Exception) {
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
                'token' => $providerUser->token ?? '',
                'refresh_token' => $providerUser->refreshToken ?? '',
                'nickname' => $providerUser->getNickname() ?? '',
                'name' => $providerUser->getName() ?? '',
                'email' => $providerUser->getEmail() ?? '',
                'avatar' => $providerUser->getAvatar() ?? '',
            ]);

            return $oauthConnection->user;
        }

        // If the username already exists in the database, append a random string to it to ensure uniqueness.
        $username = $providerUser->getName() ?? $providerUser->getNickname();
        $random = '';
        while (User::whereName($username.$random)->exists()) {
            $random = '-'.Str::random(5);
        }

        $username .= $random;

        // The user has not connected their account with this OAuth provider before, so a new connection needs to be
        // established. Check if the user has an account with the same email address that's passed in from the provider.
        // If one exists, connect that account. Otherwise, create a new one.

        return DB::transaction(function () use ($providerUser, $provider, $username) {

            $user = User::query()->firstOrCreate(['email' => $providerUser->getEmail()], [
                'name' => $username,
                'password' => null,
            ]);

            $model = $user->oAuthConnections()->create([
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'token' => $providerUser->token ?? '',
                'refresh_token' => $providerUser->refreshToken ?? '',
                'nickname' => $providerUser->getNickname() ?? '',
                'name' => $providerUser->getName() ?? '',
                'email' => $providerUser->getEmail() ?? '',
                'avatar' => $providerUser->getAvatar() ?? '',
            ]);

            $this->updateAvatar($user, $model->avatar);

            return $user;
        });
    }

    private function updateAvatar(User $user, string $avatarUrl): void
    {
        // Determine the disk to use based on the environment.
        $disk = match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public', // Local
        };

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_URL, $avatarUrl);
        $image = curl_exec($curl);

        if ($image === false) {
            Log::error('There was an error attempting to download the image. cURL error: '.curl_error($curl));

            return;
        }

        // Generate a random path for the image and ensure that it doesn't already exist.
        do {
            $relativePath = User::profilePhotoStoragePath().'/'.Str::random(40).'.webp';
        } while (Storage::disk($disk)->exists($relativePath));

        // Store the image on the disk.
        Storage::disk($disk)->put($relativePath, $image);

        // Update the user's profile photo path.
        $user->forceFill([
            'profile_photo_path' => $relativePath,
        ])->save();
    }
}
