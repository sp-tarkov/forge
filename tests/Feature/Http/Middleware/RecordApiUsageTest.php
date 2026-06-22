<?php

declare(strict_types=1);

use App\Contracts\ApiUsageStore;

it('records an API request under its route name', function (): void {
    $this->getJson('/api/v0/ping')->assertOk();

    $data = resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'));

    expect($data['requests'])->toHaveKey('api.v0.ping|GET|200')
        ->and($data['clients'])->not->toBe([]);
});

it('records unmatched API paths under a sentinel route name', function (): void {
    $this->getJson('/api/v0/this-route-does-not-exist')->assertNotFound();

    $data = resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'));

    expect(array_keys($data['requests']))->toContain('api.v0.unmatched|GET|404');
});

it('does not record when tracking is disabled', function (): void {
    config(['api.usage.enabled' => false]);

    $this->getJson('/api/v0/ping')->assertOk();

    expect(resolve(ApiUsageStore::class)->pendingBuckets())->toBe([]);
});

it('ignores requests outside the v0 API surface', function (): void {
    $this->get('/up')->assertOk();

    expect(resolve(ApiUsageStore::class)->pendingBuckets())->toBe([]);
});
