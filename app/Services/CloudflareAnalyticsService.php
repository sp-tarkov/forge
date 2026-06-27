<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads edge-side request analytics for the open API from the Cloudflare GraphQL Analytics API.
 *
 * The origin-side counters (see App\Http\Middleware\RecordApiUsage) only see traffic that reaches this server, so once
 * Cloudflare caches API responses at the edge they no longer reflect the true request volume. This service asks
 * Cloudflare directly how many API requests it handled in the trailing 24 hours and how many of those it served from
 * cache without ever touching the origin. The result is intentionally a small array of scalars so it round-trips
 * through the cache (which has object serialization disabled).
 */
final class CloudflareAnalyticsService
{
    /**
     * The Cloudflare GraphQL Analytics endpoint.
     */
    private const string GRAPHQL_ENDPOINT = 'https://api.cloudflare.com/client/v4/graphql';

    /**
     * Cache statuses that Cloudflare served from its edge without a full origin fetch, mirroring the "Cached" bucket on
     * the Cloudflare dashboard. Everything else (miss, expired, dynamic, bypass, none, ...) is treated as origin traffic.
     *
     * @var list<string>
     */
    private const array CACHED_STATUSES = ['hit', 'stale', 'revalidated', 'updating'];

    /**
     * The edge request totals for the open API over the trailing 24 hours, or null when Cloudflare analytics are not
     * configured or the request fails. A null result tells callers to leave any previously cached value untouched rather
     * than overwrite it with zeros.
     *
     * @return array{edge_total: int, cached: int, origin: int, cached_pct: float}|null
     */
    public function apiUsageLast24Hours(): ?array
    {
        // The credentials resolve from env(), so they are null when unset. Guarding with is_string() both handles that
        // and narrows the values to non-empty strings for the request below, without a null-throwing typed getter.
        $token = config('services.cloudflare.analytics_token');
        $zoneId = config('services.cloudflare.zone_id');

        if (! is_string($token) || $token === '' || ! is_string($zoneId) || $zoneId === '') {
            return null;
        }

        $until = now()->utc();
        $since = $until->subDay();
        $prefix = config('services.cloudflare.api_path_prefix', '/api/');
        $pathFilter = (is_string($prefix) ? $prefix : '/api/').'%';

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(3, 100, throw: false)
                ->withUserAgent(Str::slug(config()->string('app.name').'-'.config()->string('app.env')))
                ->withToken($token)
                ->post(self::GRAPHQL_ENDPOINT, [
                    'query' => $this->query(),
                    'variables' => [
                        'zone' => $zoneId,
                        'since' => $since->toIso8601ZuluString(),
                        'until' => $until->toIso8601ZuluString(),
                        'path' => $pathFilter,
                    ],
                ]);
        } catch (ConnectionException $connectionException) {
            Log::warning('Cloudflare analytics request failed', ['error' => $connectionException->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Cloudflare analytics request returned a non-success status', ['status' => $response->status()]);

            return null;
        }

        /** @var array<int, array{errors?: array<int, array<string, mixed>>}>|null $errors */
        $errors = $response->json('errors');
        if (! empty($errors)) {
            Log::warning('Cloudflare analytics query returned errors', ['errors' => $errors]);

            return null;
        }

        /** @var array<int, array{count?: int, dimensions?: array{cacheStatus?: string}, avg?: array{sampleInterval?: float}}>|null $groups */
        $groups = $response->json('data.viewer.zones.0.httpRequestsAdaptiveGroups');
        if ($groups === null) {
            Log::warning('Cloudflare analytics response was missing the expected request groups');

            return null;
        }

        return $this->summarize($groups);
    }

    /**
     * Reduce the per-cache-status groups into cached / origin totals. Adaptive groups are sampled, so each group's raw
     * count is scaled by its average sample interval to estimate the real number of requests.
     *
     * @param  array<int, array{count?: int, dimensions?: array{cacheStatus?: string}, avg?: array{sampleInterval?: float}}>  $groups
     * @return array{edge_total: int, cached: int, origin: int, cached_pct: float}
     */
    private function summarize(array $groups): array
    {
        $cached = 0;
        $origin = 0;

        foreach ($groups as $group) {
            $sampleInterval = $group['avg']['sampleInterval'] ?? 1.0;
            $estimated = (int) round(($group['count'] ?? 0) * $sampleInterval);
            $status = Str::lower($group['dimensions']['cacheStatus'] ?? '');

            if (in_array($status, self::CACHED_STATUSES, true)) {
                $cached += $estimated;
            } else {
                $origin += $estimated;
            }
        }

        $edgeTotal = $cached + $origin;

        return [
            'edge_total' => $edgeTotal,
            'cached' => $cached,
            'origin' => $origin,
            'cached_pct' => $edgeTotal > 0 ? round($cached / $edgeTotal * 100, 1) : 0.0,
        ];
    }

    /**
     * The GraphQL query that groups the zone's API requests by cache status over a datetime window.
     */
    private function query(): string
    {
        return <<<'GRAPHQL'
        query ($zone: String!, $since: Time!, $until: Time!, $path: String!) {
            viewer {
                zones(filter: { zoneTag: $zone }) {
                    httpRequestsAdaptiveGroups(
                        limit: 50,
                        filter: { datetime_geq: $since, datetime_leq: $until, clientRequestPath_like: $path }
                    ) {
                        count
                        dimensions { cacheStatus }
                        avg { sampleInterval }
                    }
                }
            }
        }
        GRAPHQL;
    }
}
