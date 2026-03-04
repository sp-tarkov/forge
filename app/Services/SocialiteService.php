<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Throwable;

class SocialiteService
{
    /**
     * Find an existing user by OAuth connection or create a new one.
     */
    public function findOrCreateUser(string $provider, ProviderUser $providerUser): ?User
    {
        /** @var \Laravel\Socialite\Two\User $providerUser */
        if (empty($providerUser->getEmail())) {
            Log::error('OAuth: Unable to retrieve email from provider', [
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'name' => $providerUser->getName(),
                'nickname' => $providerUser->getNickname(),
            ]);

            return null;
        }

        $oauthConnection = OAuthConnection::whereProvider($provider)
            ->whereProviderId($providerUser->getId())
            ->first();

        $mfaStatus = $this->getMfaStatus($provider, $providerUser);

        if ($oauthConnection !== null) {
            return $this->updateExistingConnection($oauthConnection, $providerUser, $mfaStatus);
        }

        return $this->createNewConnection($provider, $providerUser, $mfaStatus);
    }

    /**
     * Update an existing OAuth connection with fresh provider data.
     */
    private function updateExistingConnection(
        OAuthConnection $connection,
        ProviderUser $providerUser,
        ?bool $mfaStatus,
    ): User {
        /** @var \Laravel\Socialite\Two\User $providerUser */
        $connection->update([
            'token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken,
            'nickname' => $providerUser->getNickname() ?? '',
            'name' => $providerUser->getName() ?? '',
            'email' => $providerUser->getEmail(),
            'avatar' => $providerUser->getAvatar() ?? '',
            'mfa_enabled' => $mfaStatus,
        ]);

        return $connection->user;
    }

    /**
     * Create a new user and OAuth connection.
     */
    private function createNewConnection(string $provider, ProviderUser $providerUser, ?bool $mfaStatus): User
    {
        /** @var \Laravel\Socialite\Two\User $providerUser */
        $username = $this->generateUniqueUsername($providerUser);

        return DB::transaction(function () use ($providerUser, $provider, $username, $mfaStatus) {
            $user = User::query()->firstOrCreate(['email' => $providerUser->getEmail()], [
                'name' => $username,
                'password' => null,
            ]);

            $oAuthConnection = $user->oAuthConnections()->create([
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken,
                'nickname' => $providerUser->getNickname() ?? '',
                'name' => $providerUser->getName() ?? '',
                'email' => $providerUser->getEmail(),
                'avatar' => $providerUser->getAvatar() ?? '',
                'mfa_enabled' => $mfaStatus,
            ]);

            $this->downloadAndStoreAvatar($user, $oAuthConnection->avatar);

            return $user;
        });
    }

    /**
     * Generate a unique username from provider data, appending random characters if needed.
     */
    private function generateUniqueUsername(ProviderUser $providerUser): string
    {
        $username = $providerUser->getName() ?: $providerUser->getNickname();
        $suffix = '';

        while (User::whereName($username.$suffix)->exists()) {
            $suffix = '-'.Str::random(5);
        }

        return $username.$suffix;
    }

    /**
     * Get the MFA status from the provider user based on the provider.
     */
    private function getMfaStatus(string $provider, ProviderUser $providerUser): ?bool
    {
        return match ($provider) {
            'discord' => $providerUser->user['mfa_enabled'] ?? null,
            default => null,
        };
    }

    /**
     * Download an avatar from a URL and store it on the configured disk.
     */
    private function downloadAndStoreAvatar(User $user, string $avatarUrl): void
    {
        if ($avatarUrl === '') {
            return;
        }

        $disk = match (config('app.env')) {
            'production' => 'r2',
            default => 'public',
        };

        try {
            $response = Http::get($avatarUrl);

            if ($response->failed()) {
                Log::error('Failed to download avatar', ['url' => $avatarUrl, 'status' => $response->status()]);

                return;
            }

            do {
                $relativePath = User::profilePhotoStoragePath().'/'.Str::random(40).'.webp';
            } while (Storage::disk($disk)->exists($relativePath));

            Storage::disk($disk)->put($relativePath, $response->body());

            $user->forceFill(['profile_photo_path' => $relativePath])->save();
        } catch (Throwable $e) {
            Log::error('Failed to download and store avatar', ['url' => $avatarUrl, 'error' => $e->getMessage()]);
        }
    }
}
