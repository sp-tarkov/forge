<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Support\DataTransferObjects\ChangeDetectionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Performs HEAD requests against mod/addon download links to detect file changes.
 */
final readonly class ChangeDetectionService
{
    public function __construct(
        private DownloadSafetyService $safetyService,
    ) {}

    /**
     * Check whether the download link for a version has changed since the last check.
     */
    public function check(ModVersion|AddonVersion $version): ChangeDetectionResult
    {
        if ($version->link === '' || $version->link === '0') {
            return new ChangeDetectionResult(changed: false, unreachable: true);
        }

        $destination = $this->safetyService->validateDestination($version->link);
        if ($destination['safe'] === false) {
            Log::warning('Change detection blocked an unsafe download link', [
                'version_type' => $version::class,
                'version_id' => $version->id,
                'error' => $destination['error'] ?? 'Unsafe destination',
            ]);

            return new ChangeDetectionResult(changed: false, unreachable: true);
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withUserAgent($this->safetyService->userAgent())
                ->withOptions($this->safetyService->requestOptions($version->link, $destination['resolved_ip'] ?? null))
                ->head($version->link);

            if (! $response->successful()) {
                return new ChangeDetectionResult(
                    changed: false,
                    unreachable: true,
                    httpStatus: $response->status(),
                );
            }

            $contentLength = $response->header('Content-Length');
            $etag = $response->header('ETag');
            $lastModified = $response->header('Last-Modified');

            $newContentLength = $contentLength !== '' && is_numeric($contentLength) ? (int) $contentLength : null;
            $newEtag = $etag !== '' ? $etag : null;
            $newLastModified = $lastModified !== '' ? $lastModified : null;

            $changed = $this->hasChanged($version, $newContentLength, $newEtag, $newLastModified);

            return new ChangeDetectionResult(
                changed: $changed,
                unreachable: false,
                contentLength: $newContentLength,
                etag: $newEtag,
                lastModified: $newLastModified,
                httpStatus: $response->status(),
            );
        } catch (ConnectionException) {
            return new ChangeDetectionResult(changed: false, unreachable: true);
        } catch (Throwable $e) {
            Log::warning('Change detection HEAD request failed', [
                'version_type' => $version::class,
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            return new ChangeDetectionResult(changed: false, unreachable: true);
        }
    }

    /**
     * Determine if the remote file has changed based on HTTP header fingerprints.
     *
     * A version that has never been verified (all fingerprints null) is considered changed.
     */
    private function hasChanged(
        ModVersion|AddonVersion $version,
        ?int $newContentLength,
        ?string $newEtag,
        ?string $newLastModified,
    ): bool {
        $neverVerified = is_null($version->etag) && is_null($version->last_modified_header) && is_null($version->last_verified_at);
        if ($neverVerified) {
            return true;
        }

        if ($newEtag !== null && $version->etag !== null && $newEtag !== $version->etag) {
            return true;
        }

        if ($newLastModified !== null && $version->last_modified_header !== null && $newLastModified !== $version->last_modified_header) {
            return true;
        }

        return $newContentLength !== null && $version->content_length !== null && $newContentLength !== $version->content_length;
    }
}
