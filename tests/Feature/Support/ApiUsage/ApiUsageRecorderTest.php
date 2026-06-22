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

it('swallows and logs store failures', function (): void {
    app()->instance(ApiUsageStore::class, new class implements ApiUsageStore
    {
        public function record(string $bucket, string $dimension, int $latencyMs, string $latencyColumn, ?string $ip): void
        {
            throw new RuntimeException('redis is down');
        }

        public function pendingBuckets(): array
        {
            return [];
        }

        public function readBucket(string $bucket): array
        {
            return ['requests' => [], 'latency' => [], 'histogram' => [], 'clients' => []];
        }

        public function forgetBucket(string $bucket): void {}
    });

    Log::spy();

    resolve(ApiUsageRecorder::class)->record('api.v0.mods', 'GET', 200, 1.0, '127.0.0.1');

    Log::shouldHaveReceived('warning')->once();
});
