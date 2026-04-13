<?php

declare(strict_types=1);

use App\Services\Verification\DownloadSafetyService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new DownloadSafetyService;
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
