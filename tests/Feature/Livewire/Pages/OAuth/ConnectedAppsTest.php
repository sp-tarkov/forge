<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Livewire;

/**
 * Helper: create a Passport client owned by no one (first-party) and persist one or more access tokens for a user.
 *
 * @param  array<int, array{deviceName: string, lastUsedAt?: ?Carbon\CarbonImmutable, lastIp?: ?string}>  $devices
 */
function seedConnectedAppForUser(User $user, string $clientName, array $devices, bool $firstParty = true): Client
{
    /** @var ClientRepository $clients */
    $clients = resolve(ClientRepository::class);
    $client = $clients->createAuthorizationCodeGrantClient(
        name: $clientName,
        redirectUris: ['http://127.0.0.1/callback'],
        confidential: false,
    );

    if (! $firstParty) {
        $owner = User::factory()->create();
        $client->forceFill(['owner_id' => $owner->getKey(), 'owner_type' => $owner->getMorphClass()])->save();
    }

    foreach ($devices as $device) {
        $token = new Token;
        $token->setAttribute('id', (string) Str::random(80));
        $token->setAttribute('user_id', $user->getKey());
        $token->setAttribute('client_id', $client->getKey());
        $token->setAttribute('name', $device['deviceName']);
        $token->setAttribute('scopes', ['profile:read']);
        $token->setAttribute('revoked', false);
        $token->setAttribute('expires_at', Date::now()->addHour());
        $token->setAttribute('device_name', $device['deviceName']);
        $token->setAttribute('last_used_at', $device['lastUsedAt'] ?? null);
        $token->setAttribute('last_ip', $device['lastIp'] ?? null);
        $token->save();

        // Pair every access token with a refresh token so we can assert it gets revoked on cleanup.
        $refresh = new RefreshToken;
        $refresh->setAttribute('id', (string) Str::random(80));
        $refresh->setAttribute('access_token_id', $token->getKey());
        $refresh->setAttribute('revoked', false);
        $refresh->setAttribute('expires_at', Date::now()->addDays(90));
        $refresh->save();
    }

    return $client;
}

describe('Connected Apps', function (): void {
    it('redirects unauthenticated visitors to login', function (): void {
        $this->get(route('oauth.connected-apps'))->assertRedirect(route('login'));
    });

    it('renders an empty state when the user has no authorized apps', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('oauth.connected-apps'))
            ->assertSuccessful()
            ->assertSee('No authorized apps');
    });

    it('lists each authorized app with its active device rows', function (): void {
        $user = User::factory()->create();

        seedConnectedAppForUser($user, 'The Forge Launcher', [
            ['deviceName' => 'Desktop-PC', 'lastUsedAt' => Date::now()->subMinutes(10), 'lastIp' => '203.0.113.1'],
            ['deviceName' => 'Laptop', 'lastUsedAt' => Date::now()->subHours(3), 'lastIp' => '203.0.113.2'],
        ]);

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->assertSee('The Forge Launcher')
            ->assertSee('Desktop-PC')
            ->assertSee('Laptop')
            ->assertSee('First-party');
    });

    it('shows human-readable scope descriptions instead of raw scope ids', function (): void {
        $user = User::factory()->create();

        seedConnectedAppForUser($user, 'The Forge Launcher', [['deviceName' => 'Desktop-PC']]);

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->assertSee('Read your basic profile information (name, email, avatar, role).')
            ->assertDontSee('profile:read');
    });

    it('badges a client owned by another user as third-party', function (): void {
        $user = User::factory()->create();

        seedConnectedAppForUser($user, 'Community Tool', [['deviceName' => 'Workstation']], firstParty: false);

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->assertSee('Third-party');
    });

    it('revokes a single device without touching the others', function (): void {
        $user = User::factory()->create();

        $client = seedConnectedAppForUser($user, 'Multi-device App', [
            ['deviceName' => 'Desktop-PC'],
            ['deviceName' => 'Laptop'],
        ]);

        $desktopToken = Token::query()->where('device_name', 'Desktop-PC')->firstOrFail();
        $laptopToken = Token::query()->where('device_name', 'Laptop')->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->call('confirmRevokeDevice', $desktopToken->getKey())
            ->call('revokeDevice');

        expect($desktopToken->fresh()?->getAttribute('revoked'))->toBeTrue();
        expect($laptopToken->fresh()?->getAttribute('revoked'))->toBeFalse();

        // The paired refresh token must also be revoked so the launcher cannot mint a fresh access token.
        $desktopRefresh = RefreshToken::query()->where('access_token_id', $desktopToken->getKey())->firstOrFail();
        $laptopRefresh = RefreshToken::query()->where('access_token_id', $laptopToken->getKey())->firstOrFail();

        expect($desktopRefresh->getAttribute('revoked'))->toBeTrue();
        expect($laptopRefresh->getAttribute('revoked'))->toBeFalse();
    });

    it('revokes every device for an entire app at once', function (): void {
        $user = User::factory()->create();

        $client = seedConnectedAppForUser($user, 'Cleanup-Me', [
            ['deviceName' => 'Desktop'],
            ['deviceName' => 'Laptop'],
            ['deviceName' => 'Phone'],
        ]);

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->call('confirmRevokeClient', $client->getKey())
            ->call('revokeClient');

        expect(Token::query()->where('client_id', $client->getKey())->where('revoked', false)->count())->toBe(0);
        expect(RefreshToken::query()->whereIn('access_token_id', Token::query()->where('client_id', $client->getKey())->pluck('id'))->where('revoked', false)->count())->toBe(0);
    });

    it('refuses to revoke another users device token', function (): void {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        seedConnectedAppForUser($owner, 'Owners App', [['deviceName' => 'Desktop']]);
        $token = Token::query()->where('user_id', $owner->getKey())->firstOrFail();

        Livewire::actingAs($stranger)
            ->test('oauth.connected-apps')
            ->call('confirmRevokeDevice', $token->getKey())
            ->call('revokeDevice');

        // Token must remain unrevoked because the stranger does not own it.
        expect($token->fresh()?->getAttribute('revoked'))->toBeFalse();
    });

    it('hides revoked tokens from the list', function (): void {
        $user = User::factory()->create();

        seedConnectedAppForUser($user, 'Old App', [['deviceName' => 'Decommissioned']]);

        Token::query()->where('user_id', $user->getKey())->update(['revoked' => true]);

        Livewire::actingAs($user)
            ->test('oauth.connected-apps')
            ->assertDontSee('Decommissioned')
            ->assertSee('No authorized apps');
    });
});
