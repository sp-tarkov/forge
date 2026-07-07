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

    expect(array_keys($data['requests']))->toContain('api.v0.unmatched|GET|404')
        ->and(array_keys($data['unmatched']))->toContain('GET|404|api/v0/this-route-does-not-exist');
});

it('records CORS preflights under the preflight sentinel and not the unmatched map', function (): void {
    $this->options('/api/v0/ping', [], [
        'Origin' => 'https://example.com',
        'Access-Control-Request-Method' => 'GET',
    ])->assertNoContent();

    $data = resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'));

    expect(array_keys($data['requests']))->toContain('api.v0.preflight|OPTIONS|204')
        ->and($data['unmatched'])->toBe([]);
});

it('answers CORS preflights with the configured max age and read-only methods', function (): void {
    $this->options('/api/v0/ping', [], [
        'Origin' => 'https://example.com',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'x-client-name',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Max-Age', '7200')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET, HEAD');
});

it('does not record matched routes into the unmatched map', function (): void {
    $this->getJson('/api/v0/ping')->assertOk();

    expect(resolve(ApiUsageStore::class)->readBucket(now()->utc()->format('YmdHi'))['unmatched'])->toBe([]);
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
