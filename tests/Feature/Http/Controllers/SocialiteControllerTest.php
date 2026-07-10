<?php

declare(strict_types=1);

use App\Jobs\DownloadUserAvatar;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

describe('OAuth callback authentication', function (): void {
    it('creates a new user and attaches the OAuth provider when logging in via OAuth', function (): void {
        Queue::fake([DownloadUserAvatar::class]);

        // Mock the Socialite user.
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('provider-user-id');
        $mock->shouldReceive('getEmail')->andReturn('newuser@example.com');
        $mock->shouldReceive('getName')->andReturn('New User');
        $mock->shouldReceive('getNickname')->andReturn(null);
        $mock->shouldReceive('getAvatar')->andReturn('avatar-url');
        $mock->token = 'access-token';
        $mock->refreshToken = 'refresh-token';
        $mock->user = ['mfa_enabled' => false];

        // Mock Socialite facade.
        Socialite::shouldReceive('driver->user')->andReturn($mock);

        // Hit the callback route.
        $response = $this->get('/login/discord/callback');

        // Assert that the user was created.
        $user = User::query()->where('email', 'newuser@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('New User');

        // Assert that the OAuth provider was attached.
        $oAuthConnection = $user->oAuthConnections()->whereProvider('discord')->first();
        expect($oAuthConnection)->not->toBeNull()
            ->and($oAuthConnection->provider_id)->toBe('provider-user-id');

        // Assert the avatar download was queued for the new user.
        Queue::assertPushed(fn (DownloadUserAvatar $job): bool => $job->user->is($user)
            && $job->avatarUrl === 'avatar-url');

        // Assert the user is authenticated.
        $this->assertAuthenticatedAs($user);

        // Assert redirect to dashboard.
        $response->assertRedirect(route('dashboard'));
    });

    it('does not queue an avatar download when the provider has no avatar', function (): void {
        Queue::fake([DownloadUserAvatar::class]);

        // Mock the Socialite user.
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('provider-user-id');
        $mock->shouldReceive('getEmail')->andReturn('newuser@example.com');
        $mock->shouldReceive('getName')->andReturn('New User');
        $mock->shouldReceive('getNickname')->andReturn(null);
        $mock->shouldReceive('getAvatar')->andReturn(null);
        $mock->token = 'access-token';
        $mock->refreshToken = 'refresh-token';
        $mock->user = ['mfa_enabled' => false];

        // Mock Socialite facade.
        Socialite::shouldReceive('driver->user')->andReturn($mock);

        $this->get('/login/discord/callback');

        Queue::assertNotPushed(DownloadUserAvatar::class);
    });

    it('attaches a new OAuth provider to an existing user when logging in via OAuth', function (): void {
        Queue::fake([DownloadUserAvatar::class]);

        // Create an existing user.
        $user = User::factory()->create([
            'email' => 'existinguser@example.com',
            'name' => 'Existing User',
            'password' => Hash::make('password123'),
        ]);

        // Mock the Socialite user.
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('new-provider-user-id');
        $mock->shouldReceive('getEmail')->andReturn('existinguser@example.com');
        $mock->shouldReceive('getName')->andReturn('Existing User Updated');
        $mock->shouldReceive('getNickname')->andReturn(null);
        $mock->shouldReceive('getAvatar')->andReturn('new-avatar-url');
        $mock->token = 'new-access-token';
        $mock->refreshToken = 'new-refresh-token';
        $mock->user = ['mfa_enabled' => false];

        // Mock Socialite facade.
        Socialite::shouldReceive('driver->user')->andReturn($mock);

        // Hit the callback route.
        $response = $this->get('/login/discord/callback');

        // Refresh user data.
        $user->refresh();

        // Assert that the username was not updated.
        expect($user->name)->toBe('Existing User')
            ->and($user->name)->not->toBe('Existing User Updated');

        // Assert that the new OAuth provider was attached.
        $oauthConnection = $user->oAuthConnections()->whereProvider('discord')->first();
        expect($oauthConnection)->not->toBeNull()
            ->and($oauthConnection->provider_id)->toBe('new-provider-user-id');

        // Assert the user is authenticated.
        $this->assertAuthenticatedAs($user);

        // Assert redirect to dashboard.
        $response->assertRedirect(route('dashboard'));
    });

    it('does not queue an avatar download when an existing connection logs in again', function (): void {
        Queue::fake([DownloadUserAvatar::class]);

        // Create an existing user with an existing OAuth connection.
        $user = User::factory()->create(['email' => 'returning@example.com']);
        $user->oAuthConnections()->create([
            'provider' => 'discord',
            'provider_id' => 'returning-provider-id',
            'token' => 'old-token',
            'refresh_token' => 'old-refresh-token',
            'nickname' => '',
            'name' => 'Returning User',
            'email' => 'returning@example.com',
            'avatar' => 'old-avatar-url',
        ]);

        // Mock the Socialite user.
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('returning-provider-id');
        $mock->shouldReceive('getEmail')->andReturn('returning@example.com');
        $mock->shouldReceive('getName')->andReturn('Returning User');
        $mock->shouldReceive('getNickname')->andReturn(null);
        $mock->shouldReceive('getAvatar')->andReturn('fresh-avatar-url');
        $mock->token = 'fresh-token';
        $mock->refreshToken = 'fresh-refresh-token';
        $mock->user = ['mfa_enabled' => false];

        // Mock Socialite facade.
        Socialite::shouldReceive('driver->user')->andReturn($mock);

        $this->get('/login/discord/callback');

        Queue::assertNotPushed(DownloadUserAvatar::class);
    });
});
