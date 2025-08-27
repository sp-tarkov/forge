<?php

declare(strict_types=1);

use App\Rules\DirectDownloadLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('DirectDownloadLink validation rule', function (): void {
    it('passes validation for valid 7z download links with content-disposition', function (): void {
        Http::fake([
            'https://example.com/mod.7z' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-disposition' => 'attachment; filename="mod-1.0.0.7z"',
                'content-length' => '1048576',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failCalled = false;

        $rule->validate('link', 'https://example.com/mod.7z', function ($message) use (&$failCalled): void {
            $failCalled = true;
        });

        expect($failCalled)->toBeFalse();
        expect($rule->contentLength)->toBe(1048576);
    });

    it('passes validation for valid 7z download links with .7z URL extension', function (): void {
        Http::fake([
            'https://example.com/download/file.7z' => Http::response('', 200, [
                'content-type' => 'application/x-7z-compressed',
                'content-length' => '2097152',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failCalled = false;

        $rule->validate('link', 'https://example.com/download/file.7z', function ($message) use (&$failCalled): void {
            $failCalled = true;
        });

        expect($failCalled)->toBeFalse();
        expect($rule->contentLength)->toBe(2097152);
    });

    it('fails validation for non-string values', function (): void {
        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', null, function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('valid URL');
    });

    it('fails validation for invalid URLs', function (): void {
        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'not-a-url', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('valid URL');
    });

    it('fails validation for inaccessible URLs', function (): void {
        Http::fake([
            'https://example.com/notfound.7z' => Http::response('', 404),
        ]);

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/notfound.7z', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('not accessible');
    });

    it('fails validation for wrong content-type', function (): void {
        Http::fake([
            'https://example.com/webpage.7z' => Http::response('', 200, [
                'content-type' => 'text/html',
                'content-disposition' => 'attachment; filename="file.7z"',
                'content-length' => '1024',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/webpage.7z', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('direct download link');
    });

    it('fails validation when URL does not end with .7z and no valid content-disposition', function (): void {
        Http::fake([
            'https://example.com/download' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-length' => '1024',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/download', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('7-zip (.7z) file');
    });

    it('fails validation for missing content-length', function (): void {
        Http::fake([
            'https://example.com/file.7z' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/file.7z', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('valid file size');
    });

    it('fails validation for zero content-length', function (): void {
        Http::fake([
            'https://example.com/empty.7z' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-length' => '0',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/empty.7z', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('valid file size');
    });

    it('handles connection exceptions gracefully', function (): void {
        Http::fake(function (): void {
            throw new ConnectionException('Connection failed');
        });

        $rule = new DirectDownloadLink;
        $failMessage = null;

        $rule->validate('link', 'https://example.com/file.7z', function ($message) use (&$failMessage): void {
            $failMessage = $message;
        });

        expect($failMessage)->not->toBeNull();
        expect($failMessage)->toContain('Unable to connect');
    });

    it('passes validation with content-disposition attachment and different filename case', function (): void {
        Http::fake([
            'https://example.com/download' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-disposition' => 'Attachment; filename="MyMod.7Z"',
                'content-length' => '5242880',
            ]),
        ]);

        $rule = new DirectDownloadLink;
        $failCalled = false;

        $rule->validate('link', 'https://example.com/download', function ($message) use (&$failCalled): void {
            $failCalled = true;
        });

        expect($failCalled)->toBeFalse();
        expect($rule->contentLength)->toBe(5242880);
    });
});
