<?php

declare(strict_types=1);

namespace App\Support\ApiUsage;

use App\Contracts\ApiUsageStore;
use App\Enums\Api\V0\ApiLatencyBucket;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a single API request as aggregate counters.
 *
 * This sits between the tracking middleware and the {@see ApiUsageStore}. It derives the minute bucket, the
 * `{route}|{method}|{status}` dimension and the latency histogram bucket, then hands them to the store. Any failure
 * is swallowed and logged so a counter problem can never break an API response.
 */
final readonly class ApiUsageRecorder
{
    public function __construct(private ApiUsageStore $store) {}

    /**
     * Record one API request.
     */
    public function record(string $routeName, string $method, int $status, float $latencyMs, ?string $ip): void
    {
        try {
            $bucket = now()->utc()->format('YmdHi');
            $dimension = sprintf('%s|%s|%d', $routeName, $method, $status);
            $roundedLatencyMs = (int) round(max(0.0, $latencyMs));
            $latencyColumn = ApiLatencyBucket::forLatency($latencyMs)->column();

            $this->store->record($bucket, $dimension, $roundedLatencyMs, $latencyColumn, $ip);
        } catch (Throwable $throwable) {
            Log::warning('Failed to record API usage', [
                'route' => $routeName,
                'status' => $status,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
