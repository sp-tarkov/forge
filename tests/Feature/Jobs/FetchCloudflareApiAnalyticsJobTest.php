<?php

declare(strict_types=1);

use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Services\CloudflareAnalyticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.cloudflare.analytics_token' => 'test-token',
        'services.cloudflare.zone_id' => 'zone-123',
        'services.cloudflare.api_path_prefix' => '/api/',
    ]);
});

it('caches the usage returned by the service', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => ['viewer' => ['zones' => [[
                'httpRequestsAdaptiveGroups' => [
                    ['count' => 900, 'dimensions' => ['cacheStatus' => 'hit'], 'avg' => ['sampleInterval' => 1]],
                    ['count' => 100, 'dimensions' => ['cacheStatus' => 'miss'], 'avg' => ['sampleInterval' => 1]],
                ],
            ]]]],
        ]),
    ]);

    (new FetchCloudflareApiAnalyticsJob)->handle(resolve(CloudflareAnalyticsService::class));

    expect(Cache::get(FetchCloudflareApiAnalyticsJob::CACHE_KEY))->toBe([
        'edge_total' => 1000,
        'cached' => 900,
        'origin' => 100,
        'cached_pct' => 90.0,
    ]);
});

it('leaves the existing cache untouched when the fetch fails', function (): void {
    $previous = ['edge_total' => 42, 'cached' => 40, 'origin' => 2, 'cached_pct' => 95.2];
    Cache::put(FetchCloudflareApiAnalyticsJob::CACHE_KEY, $previous, now()->addMinutes(15));

    Http::fake([
        'api.cloudflare.com/*' => Http::response('error', 500),
    ]);

    (new FetchCloudflareApiAnalyticsJob)->handle(resolve(CloudflareAnalyticsService::class));

    expect(Cache::get(FetchCloudflareApiAnalyticsJob::CACHE_KEY))->toBe($previous);
});
