<?php

declare(strict_types=1);

use App\Enums\OAuthClientEventType;
use App\Models\OAuthClientEvent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Livewire;

function seedClientWithTokens(?User $owner, string $name): Client
{
    /** @var ClientRepository $clients */
    $clients = resolve(ClientRepository::class);
    $client = $clients->createAuthorizationCodeGrantClient(
        name: $name,
        redirectUris: ['http://127.0.0.1/callback'],
        confidential: false,
    );

    if ($owner instanceof User) {
        $client->forceFill(['owner_id' => $owner->getKey(), 'owner_type' => $owner->getMorphClass()])->save();
    }

    // Pair the client with a single active token + refresh token so revocation has work to do.
    $tokenId = Str::random(80);
    $token = new Token;
    $token->setAttribute('id', $tokenId);
    $token->setAttribute('user_id', $owner?->getKey() ?? User::factory()->create()->getKey());
    $token->setAttribute('client_id', $client->getKey());
    $token->setAttribute('name', 'admin-revoke-test');
    $token->setAttribute('scopes', ['profile:read']);
    $token->setAttribute('revoked', false);
    $token->setAttribute('expires_at', now()->addHour());
    $token->save();

    $refresh = new RefreshToken;
    $refresh->setAttribute('id', Str::random(80));
    $refresh->setAttribute('access_token_id', $tokenId);
    $refresh->setAttribute('revoked', false);
    $refresh->setAttribute('expires_at', now()->addDays(90));
    $refresh->save();

    return $client;
}

describe('Admin OAuth client moderation', function (): void {
    it('forbids non-admins from accessing the page', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.oauth-clients'))
            ->assertForbidden();
    });

    it('renders for admin users', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.oauth-clients'))
            ->assertSuccessful()
            ->assertSee('OAuth Client Moderation');
    });

    it('lists every OAuth client with token counts', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        seedClientWithTokens($owner, 'Third-party App');
        seedClientWithTokens(null, 'First-party App');

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->assertSee('Third-party App')
            ->assertSee('First-party App');
    });

    it('filters by party (first-party only)', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        seedClientWithTokens($owner, 'Third-party App');
        seedClientWithTokens(null, 'First-party App');

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->set('partyFilter', 'first_party')
            ->assertSee('First-party App')
            ->assertDontSee('Third-party App');
    });

    it('filters by status (revoked only)', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $activeClient = seedClientWithTokens($owner, 'Still Active');
        $revokedClient = seedClientWithTokens($owner, 'Already Revoked');
        $revokedClient->forceFill(['revoked' => true])->save();

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->set('statusFilter', 'revoked')
            ->assertSee('Already Revoked')
            ->assertDontSee('Still Active');
    });

    it('searches by name fragment', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        seedClientWithTokens($owner, 'Acme Launcher');
        seedClientWithTokens($owner, 'Other App');

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->set('search', 'Acme')
            ->assertSee('Acme Launcher')
            ->assertDontSee('Other App');
    });

    it('admin-revokes a client and revokes every active token + refresh token', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $client = seedClientWithTokens($owner, 'Misbehaving App');
        $token = Token::query()->where('client_id', $client->getKey())->firstOrFail();
        $refresh = RefreshToken::query()->where('access_token_id', $token->getKey())->firstOrFail();

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->call('confirmRevocation', $client->getKey())
            ->call('revokeClient');

        expect($client->fresh()?->getAttribute('revoked'))->toBeTrue();
        expect($token->fresh()?->getAttribute('revoked'))->toBeTrue();
        expect($refresh->fresh()?->getAttribute('revoked'))->toBeTrue();

        // ADMIN_REVOKED event is recorded with the admin as actor.
        $event = OAuthClientEvent::query()
            ->where('event', OAuthClientEventType::ADMIN_REVOKED->value)
            ->where('client_id', $client->getKey())
            ->firstOrFail();
        expect($event->getAttribute('actor_user_id'))->toBe($admin->getKey());
    });

    it('exposes the audit trail for a client', function (): void {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $client = seedClientWithTokens($owner, 'Tracked App');
        OAuthClientEvent::query()->create([
            'client_id' => $client->getKey(),
            'actor_user_id' => $owner->getKey(),
            'event' => OAuthClientEventType::CREATED,
            'ip' => '203.0.113.1',
            'user_agent' => 'TestAgent/1.0',
            'metadata' => ['confidential' => true],
        ]);

        Livewire::actingAs($admin)
            ->test('pages::admin.oauth-client-moderation')
            ->call('showAuditLog', $client->getKey())
            ->assertSee(OAuthClientEventType::CREATED->value)
            ->assertSee('203.0.113.1');
    });
});
