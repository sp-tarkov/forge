<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DownloadUserAvatar;
use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;

final class SocialiteService
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

            if ($oAuthConnection->avatar !== '') {
                dispatch(new DownloadUserAvatar($user, $oAuthConnection->avatar))->afterCommit();
            }

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
        /** @var \Laravel\Socialite\Two\User $providerUser */
        $userData = (array) $providerUser->user;

        return match ($provider) {
            'discord' => isset($userData['mfa_enabled']) ? (bool) $userData['mfa_enabled'] : null,
            default => null,
        };
    }
}
