<?php

declare(strict_types=1);

use App\Contracts\DnsResolver;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Services\Verification\ChangeDetectionService;
use App\Support\Dns\ArrayDnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = resolve(ChangeDetectionService::class);
    $this->mod = Mod::factory()->for(User::factory(), 'owner')->create();
});

it('detects change when version has never been verified', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"abc123"',
        ]),
    ]);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://example.com/mod.zip',
        'etag' => null,
        'last_modified_header' => null,
        'last_verified_at' => null,
    ]);

    $result = $this->service->check($modVersion);

    expect($result->changed)->toBeTrue();
    expect($result->unreachable)->toBeFalse();
    expect($result->contentLength)->toBe(12345);
    expect($result->etag)->toBe('"abc123"');
});

it('detects change when etag differs', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'ETag' => '"new-etag"',
        ]),
    ]);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://example.com/mod.zip',
        'etag' => '"old-etag"',
        'last_verified_at' => now(),
    ]);

    $result = $this->service->check($modVersion);

    expect($result->changed)->toBeTrue();
});

it('detects no change when fingerprints match', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
            'Last-Modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
        ]),
    ]);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://example.com/mod.zip',
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_modified_header' => 'Wed, 01 Jan 2025 00:00:00 GMT',
        'last_verified_at' => now(),
    ]);

    $result = $this->service->check($modVersion);

    expect($result->changed)->toBeFalse();
});

it('marks as unreachable when request fails', function (): void {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();
    expect($result->changed)->toBeFalse();
});

it('marks as unreachable when connection fails', function (): void {
    Http::fake([
        '*' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://93.184.215.14/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();
});

it('returns unreachable for empty link', function (): void {
    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => '',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();
    expect($result->changed)->toBeFalse();
});

it('never requests a link that resolves to an internal address', function (string $link): void {
    Http::fake();

    $modVersion = ModVersion::factory()->for($this->mod)->create(['link' => $link]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();
    expect($result->changed)->toBeFalse();

    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/mod.zip',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/mod.zip',
    'private range' => 'http://192.168.1.1/mod.zip',
    'link local' => 'http://10.0.0.1/mod.zip',
]);

it('never requests a link targeting a non-web port', function (): void {
    Http::fake();

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'http://15.235.87.67:6379/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();

    Http::assertNothingSent();
});

it('never requests a link with a non-http scheme', function (): void {
    Http::fake();

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'file:///etc/passwd',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();

    Http::assertNothingSent();
});

it('never requests a hostname that resolves to an internal address', function (): void {
    Http::fake();

    /** @var ArrayDnsResolver $resolver */
    $resolver = resolve(DnsResolver::class);
    $resolver->set('rebind.example.com', ['169.254.169.254']);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://rebind.example.com/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();

    Http::assertNothingSent();
});

it('never requests a hostname where only one of several addresses is internal', function (): void {
    Http::fake();

    /** @var ArrayDnsResolver $resolver */
    $resolver = resolve(DnsResolver::class);
    $resolver->set('mixed.example.com', ['93.184.215.14', '127.0.0.1']);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://mixed.example.com/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();

    Http::assertNothingSent();
});

it('marks as unreachable when the hostname does not resolve', function (): void {
    Http::fake();

    /** @var ArrayDnsResolver $resolver */
    $resolver = resolve(DnsResolver::class);
    $resolver->set('dead.example.com', []);

    $modVersion = ModVersion::factory()->for($this->mod)->create([
        'link' => 'https://dead.example.com/mod.zip',
    ]);

    $result = $this->service->check($modVersion);

    expect($result->unreachable)->toBeTrue();

    Http::assertNothingSent();
});
