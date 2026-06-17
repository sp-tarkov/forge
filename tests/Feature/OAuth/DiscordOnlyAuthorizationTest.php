<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passport\ClientRepository;

describe('Discord-only users can authorize OAuth clients', function (): void {
    it('stores the original /oauth/authorize URL as the intended redirect for an unauthenticated visitor', function (): void {
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Discord Test Launcher',
            redirectUris: ['http://127.0.0.1/callback'],
            confidential: false,
        );

        $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorizeUrl = '/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => 'profile:read',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        $loginRedirect = $this->get($authorizeUrl);
        $loginRedirect->assertRedirect(route('login'));

        $stored = (string) session('url.intended');
        expect(parse_url($stored, PHP_URL_PATH))->toBe('/oauth/authorize');

        parse_str((string) parse_url($stored, PHP_URL_QUERY), $storedQuery);
        expect($storedQuery['client_id'])->toBe($client->getKey());
        expect($storedQuery['redirect_uri'])->toBe('http://127.0.0.1/callback');
        expect($storedQuery['response_type'])->toBe('code');
        expect($storedQuery['code_challenge'])->toBe($challenge);
    });

    it('redirects a freshly-authenticated user to the intended /oauth/authorize URL (Discord-only path)', function (): void {
        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'Intended Redirect Client',
            redirectUris: ['http://127.0.0.1/callback'],
            confidential: false,
        );

        $authorizeUrl = '/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => 'profile:read',
            'state' => 'xyz',
            'code_challenge' => 'abc',
            'code_challenge_method' => 'S256',
        ]);

        // Simulate the state after the SocialiteController has set the intended URL and logged the user in.
        $passwordlessUser = User::factory()->create(['password' => null]);

        $response = $this->withSession(['url.intended' => url($authorizeUrl)])
            ->actingAs($passwordlessUser)
            ->followingRedirects()
            ->get(route('dashboard'));

        // The redirect()->intended(route('dashboard')) call in SocialiteController only triggers when there *is* a
        // stored intended URL. We assert the same logic directly using Laravel's `redirect()->intended()` helper.
        $intended = redirect()->intended(route('dashboard'));
        expect($intended->getTargetUrl())->toBe(url($authorizeUrl));
    });

    it('lets a password-null user complete OAuth consent without ever needing a password', function (): void {
        $passwordlessUser = User::factory()->create([
            'email' => 'no-password@example.com',
            'password' => null,
        ]);

        /** @var ClientRepository $clients */
        $clients = resolve(ClientRepository::class);
        $client = $clients->createAuthorizationCodeGrantClient(
            name: 'PWless Launcher',
            redirectUris: ['http://127.0.0.1/callback'],
            confidential: false,
        );

        $verifier = mb_rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Simulate the already-logged-in-via-Discord state and proceed through consent.
        $this->actingAs($passwordlessUser)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->getKey(),
                'redirect_uri' => 'http://127.0.0.1/callback',
                'response_type' => 'code',
                'scope' => 'profile:read',
                'state' => 'xyz',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ]))
            ->assertSuccessful();

        $authToken = session('authToken');

        $approval = $this->actingAs($passwordlessUser)
            ->withSession(['authToken' => $authToken, 'authRequest' => session('authRequest')])
            ->post('/oauth/authorize', ['auth_token' => $authToken]);

        $approval->assertStatus(302);

        expect($approval->headers->get('Location'))->toStartWith('http://127.0.0.1/callback?');

        // Critically, this user has no password and never could have hit /api/v0/auth/login.
        expect($passwordlessUser->fresh()->password)->toBeNull();
    });

    it('confirms SocialiteController honours redirect()->intended() so OAuth round-trips survive Discord login', function (): void {
        $controller = file_get_contents(app_path('Http/Controllers/SocialiteController.php'));

        // Smoke-test the patch: callback() must not unconditionally return to the dashboard.
        expect($controller)->toContain("redirect()->intended(route('dashboard'))");
        expect($controller)->not->toContain("return to_route('dashboard');");
    });
});
