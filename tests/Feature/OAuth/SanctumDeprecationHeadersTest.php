<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;

describe('Sanctum deprecation headers', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('emits Deprecation, Sunset, and Link headers from /api/v0/auth/login', function (): void {
        User::factory()->create([
            'email' => 'pat@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v0/auth/login', [
            'email' => 'pat@example.com',
            'password' => 'password123',
        ]);

        $response->assertHeader('Deprecation', 'Thu, 28 May 2026 00:00:00 GMT');
        $response->assertHeader('Sunset', 'Sun, 29 Nov 2026 00:00:00 GMT');

        expect((string) $response->headers->get('Link'))->toContain('rel="successor-version"');
        expect((string) $response->headers->get('Link'))->toContain('rel="deprecation"');
    });

    it('emits the headers even when login fails (so misconfigured clients still see the warning)', function (): void {
        $response = $this->postJson('/api/v0/auth/login', [
            'email' => 'doesnotexist@example.com',
            'password' => 'wrong',
        ]);

        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
    });

    it('emits the headers on /api/v0/auth/logout', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('legacy', ['read'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v0/auth/logout');

        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
    });

    it('emits the headers on /api/v0/auth/logout/all', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('legacy', ['read'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v0/auth/logout/all');

        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
    });

    it('does NOT emit deprecation headers on the new OAuth endpoints', function (): void {
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'bogus',
            'code' => 'bogus',
        ]);

        expect($response->headers->has('Deprecation'))->toBeFalse();
        expect($response->headers->has('Sunset'))->toBeFalse();
    });

    it('does NOT emit deprecation headers on the still-supported read endpoints', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('legacy', ['read'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v0/auth/user');

        expect($response->headers->has('Deprecation'))->toBeFalse();
        expect($response->headers->has('Sunset'))->toBeFalse();
    });
});
