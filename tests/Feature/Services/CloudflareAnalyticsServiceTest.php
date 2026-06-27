<?php

declare(strict_types=1);

use App\Services\CloudflareAnalyticsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.cloudflare.analytics_token' => 'test-token',
        'services.cloudflare.zone_id' => 'zone-123',
        'services.cloudflare.api_path_prefix' => '/api/',
    ]);
});

it('returns null when not configured', function (): void {
    config([
        'services.cloudflare.analytics_token' => null,
        'services.cloudflare.zone_id' => null,
    ]);

    expect(resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours())->toBeNull();
});

it('summarizes cached and origin requests from the edge', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => ['viewer' => ['zones' => [[
                'httpRequestsAdaptiveGroups' => [
                    ['count' => 100, 'dimensions' => ['cacheStatus' => 'hit'], 'avg' => ['sampleInterval' => 1]],
                    ['count' => 25, 'dimensions' => ['cacheStatus' => 'stale'], 'avg' => ['sampleInterval' => 1]],
                    ['count' => 10, 'dimensions' => ['cacheStatus' => 'miss'], 'avg' => ['sampleInterval' => 2]],
                ],
            ]]]],
        ]),
    ]);

    $usage = resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours();

    // Cached = 100 + 25; origin = 10 * sampleInterval(2); total = 145; cached share = 125/145.
    expect($usage['edge_total'])->toBe(145)
        ->and($usage['cached'])->toBe(125)
        ->and($usage['origin'])->toBe(20)
        ->and($usage['cached_pct'])->toBe(86.2);
});

it('sends the configured path filter and bearer token', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => ['viewer' => ['zones' => [['httpRequestsAdaptiveGroups' => []]]]],
        ]),
    ]);

    resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && data_get($request->data(), 'variables.path') === '/api/%'
        && data_get($request->data(), 'variables.zone') === 'zone-123');
});

it('returns zeros for a valid response with no traffic', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => ['viewer' => ['zones' => [['httpRequestsAdaptiveGroups' => []]]]],
        ]),
    ]);

    expect(resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours())->toBe([
        'edge_total' => 0,
        'cached' => 0,
        'origin' => 0,
        'cached_pct' => 0.0,
    ]);
});

it('returns null when the query returns GraphQL errors', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => null,
            'errors' => [['message' => 'Authentication error']],
        ]),
    ]);

    expect(resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours())->toBeNull();
});

it('returns null on a non-success response', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('server error', 500),
    ]);

    expect(resolve(CloudflareAnalyticsService::class)->apiUsageLast24Hours())->toBeNull();
});
