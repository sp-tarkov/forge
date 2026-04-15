<?php

declare(strict_types=1);

namespace App\Services\Verification;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Validates download URLs before fetching them to prevent SSRF attacks and enforce file size limits.
 */
final class DownloadSafetyService
{
    /**
     * CIDR ranges that must be blocked to prevent SSRF.
     *
     * @var list<string>
     */
    private const array BLOCKED_CIDR_RANGES = [
        '0.0.0.0/8',          // "This" network
        '10.0.0.0/8',         // Private (RFC 1918)
        '100.64.0.0/10',      // Carrier-grade NAT (RFC 6598)
        '127.0.0.0/8',        // Loopback
        '169.254.0.0/16',     // Link-local / cloud metadata
        '172.16.0.0/12',      // Private (RFC 1918)
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // Documentation (TEST-NET-1)
        '192.88.99.0/24',     // 6to4 relay anycast
        '192.168.0.0/16',     // Private (RFC 1918)
        '198.18.0.0/15',      // Benchmarking
        '198.51.100.0/24',    // Documentation (TEST-NET-2)
        '203.0.113.0/24',     // Documentation (TEST-NET-3)
        '224.0.0.0/4',        // Multicast
        '240.0.0.0/4',        // Reserved
        '255.255.255.255/32', // Broadcast
        '::1/128',            // IPv6 loopback
        'fc00::/7',           // IPv6 unique local
        'fe80::/10',          // IPv6 link-local
        '::ffff:0:0/96',      // IPv4-mapped IPv6
    ];

    /**
     * Validate a URL is safe to download from and within size limits.
     *
     * @return array{safe: bool, error?: string, content_length?: int|null}
     */
    public function validate(string $url, int $maxFileSize): array
    {
        $urlError = $this->validateUrlStructure($url);
        if ($urlError !== null) {
            return ['safe' => false, 'error' => $urlError];
        }

        /** @var string $host */
        $host = parse_url($url, PHP_URL_HOST);

        $ipError = $this->checkHostIp($host);
        if ($ipError !== null) {
            return ['safe' => false, 'error' => $ipError];
        }

        return $this->validateWithHeadRequest($url, $maxFileSize);
    }

    /**
     * Validate the URL has a valid structure, host, and scheme.
     */
    private function validateUrlStructure(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return 'Invalid URL: no host found';
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'Invalid URL scheme: only HTTP and HTTPS are allowed';
        }

        return null;
    }

    /**
     * Perform a HEAD request to verify the URL serves a direct archive download within size limits.
     *
     * @return array{safe: bool, error?: string, content_length?: int|null}
     */
    private function validateWithHeadRequest(string $url, int $maxFileSize): array
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withoutVerifying()
                ->withOptions(['allow_redirects' => ['max' => 5, 'on_redirect' => function (mixed $request, mixed $response, mixed $uri): void {
                    /** @var UriInterface $uri */
                    $redirectHost = (string) $uri->getHost();
                    $ipCheck = $this->checkHostIp($redirectHost);
                    throw_if($ipCheck !== null, RuntimeException::class, 'Redirect blocked: '.$ipCheck);
                }]])
                ->head($url);

            if (! $response->successful()) {
                return ['safe' => false, 'error' => 'HEAD request returned HTTP '.$response->status()];
            }

            $contentTypeError = $this->validateContentType($response);
            if ($contentTypeError !== null) {
                return ['safe' => false, 'error' => $contentTypeError];
            }

            $formatError = $this->validateArchiveFormat($url, $response);
            if ($formatError !== null) {
                return ['safe' => false, 'error' => $formatError];
            }

            return $this->validateContentLength($response, $maxFileSize);

        } catch (RuntimeException $e) {
            return ['safe' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::warning('Download safety HEAD request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return ['safe' => false, 'error' => 'Failed to validate download URL: '.$e->getMessage()];
        }
    }

    /**
     * Validate the response content-type indicates a downloadable file.
     */
    private function validateContentType(Response $response): ?string
    {
        $contentType = mb_strtolower($response->header('content-type'));

        if ($contentType !== '' && ! str_starts_with($contentType, 'application/')) {
            return 'URL does not serve a downloadable file (content-type: '.$contentType.')';
        }

        return null;
    }

    /**
     * Validate the URL or content-disposition indicates a .zip or .7z archive.
     */
    private function validateArchiveFormat(string $url, Response $response): ?string
    {
        $urlLower = mb_strtolower($url);
        $hasValidExtension = str_ends_with($urlLower, '.7z') || str_ends_with($urlLower, '.zip');

        $contentDisposition = mb_strtolower($response->header('content-disposition'));
        $hasValidDisposition = $contentDisposition !== '' && str_contains($contentDisposition, 'attachment')
            && (str_contains($contentDisposition, '.7z') || str_contains($contentDisposition, '.zip'));

        if (! $hasValidExtension && ! $hasValidDisposition) {
            return 'URL does not point to a .zip or .7z file';
        }

        return null;
    }

    /**
     * Validate the content-length is within acceptable limits.
     *
     * @return array{safe: bool, error?: string, content_length?: int|null}
     */
    private function validateContentLength(Response $response, int $maxFileSize): array
    {
        $contentLength = $response->header('Content-Length');
        $reportedSize = ($contentLength !== '' && is_numeric($contentLength)) ? (int) $contentLength : null;

        if ($reportedSize !== null && $reportedSize > $maxFileSize) {
            return ['safe' => false, 'error' => 'File size ('.$reportedSize.' bytes) exceeds maximum ('.$maxFileSize.' bytes)'];
        }

        if ($reportedSize !== null && $reportedSize <= 0) {
            return ['safe' => false, 'error' => 'File reports zero or negative content length'];
        }

        return ['safe' => true, 'content_length' => $reportedSize];
    }

    /**
     * Resolve a hostname and check all resulting IPs against blocked CIDR ranges.
     */
    private function checkHostIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                return 'Download URL resolves to a blocked IP address';
            }

            return null;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            return 'Could not resolve hostname: '.$host;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $this->isBlockedIp($ip)) {
                return 'Download URL resolves to a blocked IP address';
            }
        }

        return null;
    }

    /**
     * Check if an IP address falls within any blocked CIDR range.
     */
    private function isBlockedIp(string $ip): bool
    {
        return array_any(self::BLOCKED_CIDR_RANGES, fn (string $cidr): bool => $this->ipInCidr($ip, $cidr));
    }

    /**
     * Check if an IP address is within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $maskBits = (int) $mask;

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // Using '8bit' encoding because inet_pton returns raw binary data.
        if (mb_strlen($ipBinary, '8bit') !== mb_strlen($subnetBinary, '8bit')) {
            return false;
        }

        $byteLength = mb_strlen($ipBinary, '8bit');
        $fullBytes = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        for ($i = 0; $i < $fullBytes && $i < $byteLength; $i++) {
            if ($ipBinary[$i] !== $subnetBinary[$i]) {
                return false;
            }
        }

        if ($remainingBits > 0 && $fullBytes < $byteLength) {
            $mask = 0xFF << (8 - $remainingBits) & 0xFF;

            return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
        }

        return true;
    }
}
