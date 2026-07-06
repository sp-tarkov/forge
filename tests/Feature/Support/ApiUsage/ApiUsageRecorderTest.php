<?php

declare(strict_types=1);

use App\Contracts\ApiUsageStore;
use App\Support\ApiUsage\ApiUsageRecorder;
use Illuminate\Support\Facades\Log;

it('records a request into the store', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.mods', 'GET', 200, 42.0, '203.0.113.5');

    $data = resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'));

    expect($data['requests'])->toBe(['api.v0.mods|GET|200' => 1])
        ->and($data['latency'])->toBe(['api.v0.mods|GET|200' => 42])
        ->and($data['histogram'])->toBe(['api.v0.mods|GET|200|lat_b3' => 1])
        ->and($data['clients'])->toBe(['203.0.113.5' => 1]);
});

it('rounds latency to whole milliseconds', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.ping', 'GET', 200, 12.6, '203.0.113.5');

    expect(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['latency'])
        ->toBe(['api.v0.ping|GET|200' => 13]);
});

it('skips the client map when the ip is null', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.ping', 'GET', 200, 1.0, null);

    expect(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['clients'])->toBe([]);
});

it('records an unmatched path with the path segment last', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.unmatched', 'GET', 404, 5.0, '203.0.113.5', 'api/v0/does-not-exist');

    expect(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['unmatched'])
        ->toBe(['GET|404|api/v0/does-not-exist' => 1]);
});

it('skips the unmatched map when no path is given', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.ping', 'GET', 200, 1.0, '203.0.113.5');

    expect(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['unmatched'])->toBe([]);
});

it('truncates overlong unmatched paths to the stored column width', function (): void {
    resolve(ApiUsageRecorder::class)->record('api.v0.unmatched', 'GET', 404, 5.0, null, 'api/v0/'.str_repeat('a', 300));

    $keys = array_keys(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['unmatched']);

    expect($keys)->toHaveCount(1)
        ->and(mb_strlen(explode('|', $keys[0], 3)[2]))->toBe(191);
});

it('swallows and logs store failures', function (): void {
    app()->instance(ApiUsageStore::class, new class implements ApiUsageStore
    {
        public function record(string $bucket, string $dimension, int $latencyMs, string $latencyColumn, ?string $ip, ?string $unmatchedDimension = null): void
        {
            throw new RuntimeException('redis is down');
        }

        public function pendingBuckets(): array
        {
            return [];
        }

        public function readBucket(string $bucket): array
        {
            return ['requests' => [], 'latency' => [], 'histogram' => [], 'clients' => [], 'unmatched' => []];
        }

        public function forgetBucket(string $bucket): void {}
    });

    Log::spy();

    resolve(ApiUsageRecorder::class)->record('api.v0.mods', 'GET', 200, 1.0, '127.0.0.1');

    Log::shouldHaveReceived('warning')->once();
});
