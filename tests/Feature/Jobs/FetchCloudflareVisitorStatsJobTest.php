<?php

declare(strict_types=1);

use App\Jobs\FetchCloudflareVisitorStatsJob;
use App\Models\Visitor;
use App\Services\CloudflareAnalyticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.cloudflare.analytics_token' => 'test-token',
        'services.cloudflare.zone_id' => 'zone-123',
        'services.cloudflare.api_host' => 'forge.example.com',
    ]);
});

/**
 * Fake Cloudflare returning one request group row per distinct client address.
 */
function fakeVisitorAddresses(int $count): void
{
    $rows = array_map(
        fn (int $i): array => ['dimensions' => ['clientIP' => '198.51.100.'.$i]],
        range(1, max(1, $count)),
    );

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'data' => ['viewer' => ['zones' => [['httpRequestsAdaptiveGroups' => $count === 0 ? [] : $rows]]]],
        ]),
    ]);
}

function runVisitorStatsJob(): void
{
    (new FetchCloudflareVisitorStatsJob)->handle(resolve(CloudflareAnalyticsService::class));
}

it('caches the distinct address count for the footer', function (): void {
    fakeVisitorAddresses(3);

    runVisitorStatsJob();

    expect(Cache::get(FetchCloudflareVisitorStatsJob::CACHE_KEY))
        ->toBe(['count' => 3, 'window_minutes' => 5]);
});

it('records a new peak when the count beats the stored one', function (): void {
    Visitor::query()->create(['peak_count' => 2, 'peak_date' => now()->subMonth()]);
    fakeVisitorAddresses(5);

    runVisitorStatsJob();

    expect(Visitor::getPeakStats()['count'])->toBe(5);
});

it('leaves a higher stored peak alone', function (): void {
    $setOn = now()->subMonth();
    Visitor::query()->create(['peak_count' => 900, 'peak_date' => $setOn]);
    fakeVisitorAddresses(5);

    runVisitorStatsJob();

    $peak = Visitor::getPeakStats();

    expect($peak['count'])->toBe(900)
        ->and($peak['date']->toDateString())->toBe($setOn->toDateString());
});

it('seeds the peak when none has ever been recorded', function (): void {
    fakeVisitorAddresses(7);

    runVisitorStatsJob();

    expect(Visitor::getPeakStats()['count'])->toBe(7);
});

it('leaves the cache and peak untouched when the fetch fails', function (): void {
    Cache::put(FetchCloudflareVisitorStatsJob::CACHE_KEY, ['count' => 500, 'window_minutes' => 5]);
    Visitor::query()->create(['peak_count' => 700, 'peak_date' => now()->subMonth()]);

    Http::fake(['api.cloudflare.com/*' => Http::response([], 500)]);

    runVisitorStatsJob();

    expect(Cache::get(FetchCloudflareVisitorStatsJob::CACHE_KEY))
        ->toBe(['count' => 500, 'window_minutes' => 5])
        ->and(Visitor::getPeakStats()['count'])->toBe(700);
});

it('does nothing when Cloudflare credentials are absent', function (): void {
    config(['services.cloudflare.analytics_token' => null]);
    Http::fake();

    runVisitorStatsJob();

    expect(Cache::get(FetchCloudflareVisitorStatsJob::CACHE_KEY))->toBeNull();
    Http::assertNothingSent();
});

it('scopes the query to the site host and html responses only', function (): void {
    fakeVisitorAddresses(2);

    runVisitorStatsJob();

    Http::assertSent(function ($request): bool {
        $query = $request->data()['query'];

        return str_contains($query, 'edgeResponseContentTypeName: "html"')
            && str_contains($query, 'clientRequestHTTPHost: $host')
            && $request->data()['variables']['host'] === 'forge.example.com';
    });
});
