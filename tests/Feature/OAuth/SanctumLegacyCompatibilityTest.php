<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

describe('Legacy Sanctum PAT compatibility', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('continues to issue Sanctum personal access tokens via /api/v0/auth/login', function (): void {
        $user = User::factory()->create([
            'email' => 'sanctum-legacy@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v0/auth/login', [
            'email' => 'sanctum-legacy@example.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful();
        expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->getKey(),
        ]);
    });

    it('accepts a Sanctum token with read ability on scope-protected endpoints', function (): void {
        $user = User::factory()->create();
        $newToken = $user->createSanctumToken('legacy-test', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->getJson('/api/v0/auth/user');

        $response->assertSuccessful();
    });

    it('rejects a Sanctum token missing the read ability', function (): void {
        $user = User::factory()->create();
        $newToken = $user->createSanctumToken('write-only', ['write']);

        $response = $this->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->getJson('/api/v0/auth/user');

        $response->assertForbidden();
    });

    it('revokes a Sanctum token via /api/v0/auth/logout', function (): void {
        $user = User::factory()->create();
        $newToken = $user->createSanctumToken('revoke-me', ['read']);

        $this->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->postJson('/api/v0/auth/logout')
            ->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $newToken->accessToken->getKey(),
        ]);
    });

    it('still rejects password-null users at the Sanctum login endpoint', function (): void {
        User::factory()->create([
            'email' => 'oauth-only@example.com',
            'password' => null,
        ]);

        $this->postJson('/api/v0/auth/login', [
            'email' => 'oauth-only@example.com',
            'password' => 'anything',
        ])->assertStatus(Response::HTTP_FORBIDDEN);
    });

    it('does not leak Passport tokens into Sanctum table or vice versa', function (): void {
        $user = User::factory()->create();
        $user->createSanctumToken('one', ['read']);

        expect(PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->count())->toBe(1);

        // Passport tokens live in their own table.
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    });
});
