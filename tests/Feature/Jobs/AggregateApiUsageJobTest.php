<?php

declare(strict_types=1);

use App\Contracts\ApiUsageStore;
use App\Jobs\AggregateApiUsageJob;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use App\Models\ApiUsageUnmatchedRequest;

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

it('rolls unmatched path counters into the unmatched requests table', function (): void {
    $store = resolve(ApiUsageStore::class);
    $bucket = now()->utc()->subMinute()->format('YmdHi');

    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', '203.0.113.1', 'GET|404|api/v0/nope');
    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', '203.0.113.2', 'GET|404|api/v0/nope');

    (new AggregateApiUsageJob)->handle($store);

    $row = ApiUsageUnmatchedRequest::query()->sole();

    expect($row->path)->toBe('api/v0/nope')
        ->and($row->method)->toBe('GET')
        ->and($row->status_code)->toBe(404)
        ->and($row->request_count)->toBe(2);
});

it('keeps only the busiest unmatched paths per bucket', function (): void {
    config(['api.usage.top_unmatched' => 1]);

    $store = resolve(ApiUsageStore::class);
    $bucket = now()->utc()->subMinute()->format('YmdHi');

    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', null, 'GET|404|api/v0/rare');
    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', null, 'GET|404|api/v0/common');
    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', null, 'GET|404|api/v0/common');

    (new AggregateApiUsageJob)->handle($store);

    expect(ApiUsageUnmatchedRequest::query()->pluck('path')->all())->toBe(['api/v0/common']);
});

it('preserves pipes inside an unmatched path', function (): void {
    $store = resolve(ApiUsageStore::class);
    $bucket = now()->utc()->subMinute()->format('YmdHi');

    $store->record($bucket, 'api.v0.unmatched|GET|404', 5, 'lat_b1', null, 'GET|404|api/v0/weird|path');

    (new AggregateApiUsageJob)->handle($store);

    expect(ApiUsageUnmatchedRequest::query()->sole()->path)->toBe('api/v0/weird|path');
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
