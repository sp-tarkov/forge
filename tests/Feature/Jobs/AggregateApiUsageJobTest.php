<?php

declare(strict_types=1);

use App\Contracts\ApiUsageStore;
use App\Jobs\AggregateApiUsageJob;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;

it('rolls a completed bucket into the metrics and client tables', function (): void {
    $store = resolve(ApiUsageStore::class);
    $bucket = now()->utc()->subMinute()->format('YmdHi');

    $store->record($bucket, 'api.v0.mods|GET|200', 30, 'lat_b3', '203.0.113.1');
    $store->record($bucket, 'api.v0.mods|GET|200', 50, 'lat_b3', '203.0.113.1');
    $store->record($bucket, 'api.v0.mods|GET|500', 10, 'lat_b1', '203.0.113.2');

    (new AggregateApiUsageJob)->handle($store);

    $ok = ApiUsageMetric::query()->where('route_name', 'api.v0.mods')->where('status_code', 200)->sole();

    expect(ApiUsageMetric::query()->count())->toBe(2)
        ->and($ok->request_count)->toBe(2)
        ->and($ok->latency_sum_ms)->toBe(80)
        ->and($ok->lat_b3)->toBe(2)
        ->and((int) ApiUsageClient::query()->where('ip', '203.0.113.1')->value('request_count'))->toBe(2)
        ->and($store->pendingBuckets())->not->toContain($bucket);
});

it('leaves the in-progress current minute untouched', function (): void {
    $store = resolve(ApiUsageStore::class);
    $current = now()->utc()->format('YmdHi');

    $store->record($current, 'api.v0.mods|GET|200', 10, 'lat_b1', '203.0.113.9');

    (new AggregateApiUsageJob)->handle($store);

    expect(ApiUsageMetric::query()->count())->toBe(0)
        ->and($store->pendingBuckets())->toContain($current);
});

it('is idempotent when the same bucket is rolled up twice', function (): void {
    $store = resolve(ApiUsageStore::class);
    $bucket = now()->utc()->subMinute()->format('YmdHi');

    $seed = function () use ($store, $bucket): void {
        $store->record($bucket, 'api.v0.mods|GET|200', 30, 'lat_b3', '203.0.113.1');
        $store->record($bucket, 'api.v0.mods|GET|200', 30, 'lat_b3', '203.0.113.1');
    };

    $seed();
    (new AggregateApiUsageJob)->handle($store);

    // The first run discards the bucket; re-seed identical data and run again.
    $seed();
    (new AggregateApiUsageJob)->handle($store);

    expect(ApiUsageMetric::query()->count())->toBe(1)
        ->and(ApiUsageMetric::query()->sole()->request_count)->toBe(2);
});

it('discards malformed bucket identifiers', function (): void {
    $store = resolve(ApiUsageStore::class);
    $store->record('not-a-bucket', 'api.v0.mods|GET|200', 10, 'lat_b1', '203.0.113.1');

    (new AggregateApiUsageJob)->handle($store);

    expect(ApiUsageMetric::query()->count())->toBe(0)
        ->and($store->pendingBuckets())->toBe([]);
});
