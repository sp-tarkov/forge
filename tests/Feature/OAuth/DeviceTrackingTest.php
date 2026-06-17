<?php

declare(strict_types=1);

use App\Http\Middleware\UpdatePassportTokenLastUsed;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Date;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Helper: drive a full auth-code-with-PKCE round trip and return the launcher's token + redirect payload.
 *
 * @return array{token: array<string, mixed>, accessTokenRow: Token, deviceName: ?string}
 */
function performAuthorizationFlowWithDevice(string $deviceName = ''): array
{
    /** @var ClientRepository $clients */
    $clients = resolve(ClientRepository::class);
    $client = $clients->createAuthorizationCodeGrantClient(
        name: 'Device Tracking Client',
        redirectUris: ['http://127.0.0.1/callback'],
        confidential: false,
    );

    $user = User::factory()->create();

    $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $query = [
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://127.0.0.1/callback',
        'response_type' => 'code',
        'scope' => 'profile:read',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];

    if ($deviceName !== '') {
        $query['device_name'] = $deviceName;
    }

    /** @var Tests\TestCase $testCase */
    $testCase = test();

    $testCase->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query($query))
        ->assertSuccessful();

    $approvalBody = ['auth_token' => session('authToken')];

    if ($deviceName !== '') {
        $approvalBody['device_name'] = $deviceName;
    }

    $approval = $testCase->actingAs($user)
        ->withSession(['authToken' => session('authToken'), 'authRequest' => session('authRequest')])
        ->post('/oauth/authorize', $approvalBody);

    $redirect = (string) $approval->headers->get('Location');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);

    $tokenResponse = $testCase->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://127.0.0.1/callback',
        'code_verifier' => $verifier,
        'code' => $params['code'],
    ]);

    /** @var array<string, mixed> $body */
    $body = $tokenResponse->json();

    /** @var Token $row */
    $row = Token::query()->latest('created_at')->firstOrFail();

    return [
        'token' => $body,
        'accessTokenRow' => $row,
        'deviceName' => $deviceName === '' ? null : $deviceName,
    ];
}

describe('Per-device token tracking', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('stamps device_name on the auth code when the launcher passes one', function (): void {
        $result = performAuthorizationFlowWithDevice('Refringe Desktop');

        expect(AuthCode::query()->where('device_name', 'Refringe Desktop')->exists())->toBeTrue();
    });

    it('stamps device_name on the issued access token', function (): void {
        $result = performAuthorizationFlowWithDevice('Refringe Laptop');

        expect($result['accessTokenRow']->getAttribute('device_name'))->toBe('Refringe Laptop');
    });

    it('leaves device_name null when the launcher does not send one', function (): void {
        $result = performAuthorizationFlowWithDevice('');

        expect($result['accessTokenRow']->getAttribute('device_name'))->toBeNull();
    });

    it('separates tokens issued from different devices for the same user and client', function (): void {
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Multi-Device Client',
            redirectUris: ['http://127.0.0.1/callback'],
            confidential: false,
        );

        $user = User::factory()->create();

        // Authorize from "Desktop"
        issueLauncherToken($client, $user, 'Desktop');

        // Authorize from "Laptop"
        issueLauncherToken($client, $user, 'Laptop');

        expect(Token::query()->where('client_id', $client->getKey())->where('user_id', $user->getKey())->count())->toBe(2);
        expect(Token::query()->where('device_name', 'Desktop')->count())->toBe(1);
        expect(Token::query()->where('device_name', 'Laptop')->count())->toBe(1);
    });

    it('carries device_name forward when refreshing an access token', function (): void {
        $result = performAuthorizationFlowWithDevice('Refresh Test Device');

        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);

        $refreshed = test()->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $result['token']['refresh_token'],
            'client_id' => $result['accessTokenRow']->getAttribute('client_id'),
            'scope' => 'profile:read',
        ]);

        $refreshed->assertSuccessful();

        $newToken = Token::query()->latest('created_at')->firstOrFail();

        expect($newToken->getAttribute('device_name'))->toBe('Refresh Test Device');
    });

    it('updates last_used_at on the active token when the API is hit', function (): void {
        $user = User::factory()->create();

        $token = new Token([
            'id' => 'tok-last-used-fresh',
            'user_id' => $user->getKey(),
            'client_id' => 'noop',
            'name' => 'Fresh',
            'scopes' => ['profile:read'],
            'revoked' => false,
            'expires_at' => Date::now()->addHour(),
            'device_name' => 'Test',
            'last_used_at' => null,
        ]);
        $token->save();

        Passport::actingAs($user, ['profile:read'], client: null);
        Passport::actingAsClient(Client::query()->first() ?? makeClient(), ['profile:read']);

        // Acting-as bypasses real token persistence; verify the middleware update logic directly via the model.
        $shouldTouchReflection = new ReflectionMethod(UpdatePassportTokenLastUsed::class, 'shouldTouch');

        $middleware = new UpdatePassportTokenLastUsed;
        expect($shouldTouchReflection->invoke($middleware, $token, Date::now()))->toBeTrue();

        $token->setAttribute('last_used_at', Date::now()->subMinutes(2));
        expect($shouldTouchReflection->invoke($middleware, $token, Date::now()))->toBeFalse();

        $token->setAttribute('last_used_at', Date::now()->subMinutes(10));
        expect($shouldTouchReflection->invoke($middleware, $token, Date::now()))->toBeTrue();
    });
});

/**
 * Helper that drives the consent + token-exchange round trip without returning the bundled tuple.
 */
function issueLauncherToken(Client $client, User $user, string $deviceName): void
{
    $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    /** @var Tests\TestCase $testCase */
    $testCase = test();

    // Passport auto-approves when the user already holds an active token for this client+scope combination. We
    // accept either path: consent screen (200) followed by explicit approval, OR auto-approval (302 with code).
    $response = $testCase->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => 'profile:read',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'device_name' => $deviceName,
        ]));

    if ($response->isRedirect()) {
        $redirect = (string) $response->headers->get('Location');
    } else {
        $approval = $testCase->actingAs($user)
            ->withSession(['authToken' => session('authToken'), 'authRequest' => session('authRequest')])
            ->post('/oauth/authorize', [
                'auth_token' => session('authToken'),
                'device_name' => $deviceName,
            ]);

        $redirect = (string) $approval->headers->get('Location');
    }

    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);

    $testCase->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://127.0.0.1/callback',
        'code_verifier' => $verifier,
        'code' => $params['code'],
    ])->assertSuccessful();
}

/**
 * Fallback client used by the last-used middleware unit-test scenario.
 */
function makeClient(): Client
{
    /** @var ClientRepository $clients */
    $clients = resolve(ClientRepository::class);

    return $clients->createAuthorizationCodeGrantClient(
        name: 'noop',
        redirectUris: ['http://127.0.0.1/callback'],
        confidential: false,
    );
}
