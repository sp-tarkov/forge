<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

describe('OAuth endpoint rate limiting', function (): void {
    beforeEach(function (): void {
        RateLimiter::clear('oauth-token:127.0.0.1');
        RateLimiter::clear('oauth:127.0.0.1');
    });

    it('rejects /oauth/token after the per-minute cap is exhausted', function (): void {
        // The throttle middleware needs to be active for this test (we usually disable it elsewhere).
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => 'bogus',
                'code' => 'bogus',
            ]);
        }

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'bogus',
            'code' => 'bogus',
        ]);

        expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('applies the stricter limit to /oauth/token without throttling /oauth/authorize after the same number of hits', function (): void {
        // 31 hits to /oauth/authorize from an unauthenticated visitor: each just redirects to login. The authorize
        // limit is 120/min so we stay well under it.
        for ($i = 0; $i < 31; $i++) {
            $this->get('/oauth/authorize?client_id=bogus');
        }

        $response = $this->get('/oauth/authorize?client_id=bogus');

        expect($response->getStatusCode())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('does not rate-limit when tests bypass throttling middleware', function (): void {
        // Sanity check: with throttle middleware disabled, no 429 should ever come back.
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);

        for ($i = 0; $i < 35; $i++) {
            $response = $this->postJson('/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => 'bogus',
                'code' => 'bogus',
            ]);
            expect($response->getStatusCode())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
        }
    });
});
