<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

describe('Per-endpoint scope enforcement', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('lets a Passport token with profile:read read the authenticated user', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['profile:read']);

        $this->getJson('/api/v0/auth/user')->assertSuccessful();
    });

    it('rejects a Passport token without profile:read from reading the authenticated user', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['mods:read']);

        $this->getJson('/api/v0/auth/user')
            ->assertForbidden()
            ->assertJsonPath('code', ApiErrorCode::INSUFFICIENT_SCOPE->value);
    });

    it('lets a Passport token with mods:read list mods', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['mods:read']);

        // Even if no mods exist, the endpoint must respond with 200 -- not a scope rejection.
        $response = $this->getJson('/api/v0/mods');
        expect($response->status())->not->toBe(Response::HTTP_FORBIDDEN);
    });

    it('rejects a Passport token without mods:read from listing mods', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['profile:read']);

        $this->getJson('/api/v0/mods')
            ->assertForbidden()
            ->assertJsonPath('code', ApiErrorCode::INSUFFICIENT_SCOPE->value);
    });

    it('lets a Passport token with spt:read list SPT versions', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['spt:read']);

        $response = $this->getJson('/api/v0/spt/versions');
        expect($response->status())->not->toBe(Response::HTTP_FORBIDDEN);
    });

    it('rejects a Passport token with the wrong scope from listing SPT versions', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, ['mods:read']);

        $this->getJson('/api/v0/spt/versions')
            ->assertForbidden()
            ->assertJsonPath('code', ApiErrorCode::INSUFFICIENT_SCOPE->value);
    });

    it('lets any authenticated token log itself out without a scope', function (): void {
        $user = User::factory()->create();
        Passport::actingAs($user, []);

        // Logout requires only proof of authentication; no scope.
        $response = $this->postJson('/api/v0/auth/logout');
        expect($response->status())->not->toBe(Response::HTTP_FORBIDDEN);
    });

    it('returns 401 for unauthenticated requests against scope-protected endpoints', function (): void {
        $this->getJson('/api/v0/auth/user')->assertUnauthorized();
        $this->getJson('/api/v0/mods')->assertUnauthorized();
    });
});
