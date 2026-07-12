<?php

declare(strict_types=1);

use App\Services\Verification\DownloadSafetyService;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = resolve(DownloadSafetyService::class);
    $this->maxSize = 500 * 1024 * 1024;
});

it('accepts a valid direct download url', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '12345',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeTrue();
    expect($result['content_length'])->toBe(12345);
});

it('accepts a valid 7z download url', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/x-7z-compressed',
            'Content-Length' => '5000',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/mod.7z', $this->maxSize);

    expect($result['safe'])->toBeTrue();
});

it('rejects urls with non-http schemes', function (): void {
    $result = $this->service->validate('ftp://example.com/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('scheme');
});

it('rejects urls with no host', function (): void {
    $result = $this->service->validate('not-a-url', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('host');
});

it('rejects localhost urls', function (): void {
    $result = $this->service->validate('http://127.0.0.1/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('blocked IP');
});

it('rejects private network urls', function (): void {
    $result = $this->service->validate('http://192.168.1.1/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('blocked IP');
});

it('rejects cloud metadata endpoint', function (): void {
    $result = $this->service->validate('http://169.254.169.254/latest/meta-data/', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('blocked IP');
});

it('rejects 10.x.x.x private range', function (): void {
    $result = $this->service->validate('http://10.0.0.1/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('blocked IP');
});

it('rejects 172.16.x.x private range', function (): void {
    $result = $this->service->validate('http://172.16.0.1/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('blocked IP');
});

it('rejects urls that do not point to archive files', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '1000',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/page.html', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('.zip or .7z');
});

it('rejects urls serving html content type', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('downloadable file');
});

it('rejects files exceeding max size via content-length', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '999999999999',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('exceeds maximum');
});

it('rejects when head request returns non-200', function (): void {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $result = $this->service->validate('https://example.com/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('HTTP 404');
});

it('accepts urls with content-disposition attachment header', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '5000',
            'Content-Disposition' => 'attachment; filename="mymod.zip"',
        ]),
    ]);

    $result = $this->service->validate('https://example.com/download?id=123', $this->maxSize);

    expect($result['safe'])->toBeTrue();
});

it('returns the validated ip so the download can be pinned to it', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '5000',
        ]),
    ]);

    $result = $this->service->validate('https://93.184.215.14/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeTrue();
    expect($result['resolved_ip'])->toBe('93.184.215.14');
});

it('does not return a resolved ip when validation fails', function (): void {
    $result = $this->service->validate('http://127.0.0.1/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result)->not->toHaveKey('resolved_ip');
});

it('builds a curl resolve entry using the scheme default port', function (): void {
    expect($this->service->curlResolveEntry('https://example.com/mod.zip', '93.184.215.14'))
        ->toBe('example.com:443:93.184.215.14');

    expect($this->service->curlResolveEntry('http://example.com/mod.zip', '93.184.215.14'))
        ->toBe('example.com:80:93.184.215.14');
});

it('builds a curl resolve entry using an explicit port', function (): void {
    expect($this->service->curlResolveEntry('https://example.com:8443/mod.zip', '93.184.215.14'))
        ->toBe('example.com:8443:93.184.215.14');
});

it('returns no curl resolve entry for a url without a host', function (): void {
    expect($this->service->curlResolveEntry('not-a-url', '93.184.215.14'))->toBeNull();
});

it('rejects urls targeting a non-web port', function (int $port): void {
    $result = $this->service->validate('http://15.235.87.67:'.$port.'/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeFalse();
    expect($result['error'])->toContain('port');
})->with([
    'redis' => 6379,
    'mysql' => 3306,
    'ssh' => 22,
]);

it('accepts urls on an explicitly allowed port', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '5000',
        ]),
    ]);

    config()->set('verification.allowed_ports', [80, 443, 8443]);

    $result = $this->service->validate('https://example.com:8443/mod.zip', $this->maxSize);

    expect($result['safe'])->toBeTrue();
});

it('blocks a redirect hop targeting a non-web port', function (): void {
    $guard = $this->service->redirectGuard();
    $onRedirect = $guard['on_redirect'];

    expect(fn () => $onRedirect(null, null, new Uri('http://15.235.87.67:6379/')))
        ->toThrow(RuntimeException::class, 'Redirect blocked');
});

it('blocks a redirect hop that resolves to an internal address', function (): void {
    $guard = $this->service->redirectGuard();
    $onRedirect = $guard['on_redirect'];

    expect(fn () => $onRedirect(null, null, new Uri('http://169.254.169.254/latest/meta-data/')))
        ->toThrow(RuntimeException::class, 'Redirect blocked');
});

it('allows a redirect hop that resolves to a public address', function (): void {
    $guard = $this->service->redirectGuard();
    $onRedirect = $guard['on_redirect'];

    expect(fn () => $onRedirect(null, null, new Uri('https://example.com/mod.zip')))->not->toThrow(RuntimeException::class);
});
