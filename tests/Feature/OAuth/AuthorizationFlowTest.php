<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Create a fresh public (PKCE-required, no secret) Authorization-Code client used across the flow tests.
 */
function makePublicClient(string $redirectUri = 'http://127.0.0.1/callback'): Client
{
    /** @var ClientRepository $clients */
    $clients = resolve(ClientRepository::class);

    return $clients->createAuthorizationCodeGrantClient(
        name: 'Test Launcher',
        redirectUris: [$redirectUri],
        confidential: false,
    );
}

/**
 * Generate an RFC 7636 PKCE verifier/challenge pair with the S256 method.
 *
 * @return array{verifier: string, challenge: string}
 */
function makePkcePair(): array
{
    $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return ['verifier' => $verifier, 'challenge' => $challenge];
}

describe('Authorization Code + PKCE flow', function (): void {
    it('redirects unauthenticated callers from /oauth/authorize to login', function (): void {
        $client = makePublicClient();
        $pkce = makePkcePair();

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => 'profile:read',
            'state' => 'xyz',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ]));

        $response->assertRedirect(route('login'));
    });

    it('renders the consent screen for an authenticated user with the requested scopes', function (): void {
        $client = makePublicClient();
        $pkce = makePkcePair();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read mods:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]));

        $response->assertSuccessful();
        $response->assertSee('Test Launcher');
        $response->assertSee('Read your basic profile information', escape: false);
        $response->assertSee('Browse mods', escape: false);
        $response->assertSee('Authorize');
        $response->assertSee('Cancel');
    });

    it('badges a client without an owner as an official Forge application', function (): void {
        $client = makePublicClient();
        $user = User::factory()->create();
        $pkce = makePkcePair();

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]));

        $response->assertSee('Official Forge application');
    });

    it('badges a client with an owner as third-party and shows the trust warning', function (): void {
        $owner = User::factory()->create();
        $client = makePublicClient();
        $client->forceFill(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()])->save();

        $user = User::factory()->create();
        $pkce = makePkcePair();

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]));

        $response->assertSee('Third-party application');
        $response->assertSee('Only approve if you trust this application', escape: false);
    });

    it('issues an authorization code that exchanges for an access token', function (): void {
        $client = makePublicClient();
        $user = User::factory()->create();
        $pkce = makePkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read mods:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]))
            ->assertSuccessful();

        $authToken = session('authToken');
        expect($authToken)->toBeString()->not->toBeEmpty();

        $approval = $this->actingAs($user)
            ->withSession(['authToken' => $authToken, 'authRequest' => session('authRequest')])
            ->post('/oauth/authorize', [
                'auth_token' => $authToken,
            ]);

        $approval->assertStatus(Response::HTTP_FOUND);

        $redirect = (string) $approval->headers->get('Location');
        expect($redirect)->toStartWith('http://127.0.0.1/callback?');

        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        expect($query)
            ->toHaveKey('code')
            ->toHaveKey('state')
            ->and($query['state'])->toBe('xyz');

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'code_verifier' => $pkce['verifier'],
            'code' => $query['code'],
        ]);

        $tokenResponse->assertSuccessful();
        $tokenResponse->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        expect($tokenResponse->json('token_type'))->toBe('Bearer');
        expect($tokenResponse->json('expires_in'))->toBe(3600);

        // Auth code is single-use; a second exchange must fail.
        $secondAttempt = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'code_verifier' => $pkce['verifier'],
            'code' => $query['code'],
        ]);

        $secondAttempt->assertStatus(Response::HTTP_BAD_REQUEST);
    });

    it('rejects token exchange when the PKCE verifier does not match the challenge', function (): void {
        $client = makePublicClient();
        $user = User::factory()->create();
        $pkce = makePkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]))
            ->assertSuccessful();

        $authToken = session('authToken');

        $approval = $this->actingAs($user)
            ->withSession(['authToken' => $authToken, 'authRequest' => session('authRequest')])
            ->post('/oauth/authorize', [
                'auth_token' => $authToken,
            ]);

        $redirect = (string) $approval->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'code_verifier' => makePkcePair()['verifier'],
            'code' => $query['code'],
        ]);

        $tokenResponse->assertStatus(Response::HTTP_BAD_REQUEST);
    });

    it('redirects with an access_denied error when the user denies consent', function (): void {
        $client = makePublicClient();
        $user = User::factory()->create();
        $pkce = makePkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]))
            ->assertSuccessful();

        $authToken = session('authToken');

        $denial = $this->actingAs($user)
            ->withSession(['authToken' => $authToken, 'authRequest' => session('authRequest')])
            ->delete('/oauth/authorize', [
                'auth_token' => $authToken,
            ]);

        $denial->assertStatus(Response::HTTP_FOUND);

        $redirect = (string) $denial->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        expect($query)->toHaveKey('error');
        expect($query['error'])->toBe('access_denied');
        expect($query['state'] ?? null)->toBe('xyz');

        // No auth code is persisted when the user denies.
        expect(AuthCode::query()->count())->toBe(0);
    });

    it('issues refresh tokens that can be exchanged for new access tokens', function (): void {
        $client = makePublicClient();
        $user = User::factory()->create();
        $pkce = makePkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]))
            ->assertSuccessful();

        $authToken = session('authToken');

        $approval = $this->actingAs($user)
            ->withSession(['authToken' => $authToken, 'authRequest' => session('authRequest')])
            ->post('/oauth/authorize', ['auth_token' => $authToken]);

        $redirect = (string) $approval->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $first = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'code_verifier' => $pkce['verifier'],
            'code' => $query['code'],
        ]);

        $first->assertSuccessful();

        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $first->json('refresh_token'),
            'client_id' => $client->getKey(),
            'scope' => 'profile:read',
        ]);

        $refreshed->assertSuccessful();
        expect($refreshed->json('access_token'))->toBeString()->not->toBe($first->json('access_token'));
    });
});
