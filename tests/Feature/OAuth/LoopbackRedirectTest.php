<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Symfony\Component\HttpFoundation\Response;

describe('Loopback redirect URI handling (RFC 8252)', function (): void {
    it('accepts any port on 127.0.0.1 when the client registers a portless loopback redirect', function (): void {
        // Register with the exact path the launcher will use; only the port may vary at runtime (RFC 8252).
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Loopback Test',
            redirectUris: ['http://127.0.0.1/callback'],
            confidential: false,
        );

        $user = User::factory()->create();
        $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1:54321/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ]));

        // The consent screen renders, meaning Passport accepted the differing-port redirect_uri.
        $response->assertSuccessful();
        $response->assertSee('Loopback Test');
    });

    it('rejects a non-loopback redirect that differs from any registered URI', function (): void {
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Strict Redirect Client',
            redirectUris: ['https://example.com/callback'],
            confidential: false,
        );

        $user = User::factory()->create();
        $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'https://attacker.example.com/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ]));

        // OAuth servers MUST NOT redirect to an unregistered URI; Passport responds with a 4xx HTML error page.
        expect($response->getStatusCode())->toBeIn([
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);
    });

    it('accepts the registered redirect URI when used verbatim', function (): void {
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Verbatim Client',
            redirectUris: ['https://example.com/oauth/callback'],
            confidential: false,
        );

        $user = User::factory()->create();
        $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'https://example.com/oauth/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ]));

        $response->assertSuccessful();
    });
});
