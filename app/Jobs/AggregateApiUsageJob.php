<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ApiUsageStore;
use App\Enums\Api\V0\ApiLatencyBucket;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use App\Models\ApiUsageUnmatchedRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains completed per-minute API usage counters out of the {@see ApiUsageStore} and into the durable rollup tables.
 *
 * Runs every minute. Each completed bucket (strictly older than the current minute, which is still being written to)
 * is read, upserted into `api_usage_metrics` / `api_usage_clients`, then discarded from the store. Upserts use SET
 * semantics keyed on the dimension columns, so the totals read from the store are authoritative and re-running the
 * job before a bucket is discarded reproduces identical rows rather than double counting.
 */
#[Timeout(60)]
#[Tries(2)]
final class AggregateApiUsageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Drain every completed bucket.
     */
    public function handle(ApiUsageStore $store): void
    {
        $currentBucket = now()->utc()->format('YmdHi');
        $topClients = config()->integer('api.usage.top_clients');

        foreach ($store->pendingBuckets() as $bucket) {
            // Discard anything that is not a well-formed minute identifier so a stray key cannot wedge the job. This
            // runs before the freshness check below because a malformed value cannot be compared meaningfully.
            if (in_array(preg_match('/^\d{12}$/', $bucket), [0, false], true)) {
                $store->forgetBucket($bucket);

                continue;
            }

            // The current (and any future) minute is still receiving writes; leave it until it is complete.
            if ($bucket >= $currentBucket) {
                continue;
            }

            try {
                $this->rollUpBucket($store, $bucket, $topClients);
                $store->forgetBucket($bucket);
            } catch (Throwable $throwable) {
                // Leave the bucket in place so the next run retries it; only successful persistence discards it.
                Log::error('Failed to roll up API usage bucket', [
                    'bucket' => $bucket,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('AggregateApiUsageJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Persist a single bucket's counters into the rollup tables.
     */
    private function rollUpBucket(ApiUsageStore $store, string $bucket, int $topClients): void
    {
        $data = $store->readBucket($bucket);
        // The leading `!` resets seconds/micros to zero so every minute row lands cleanly on its minute boundary.
        $periodStart = CarbonImmutable::createFromFormat('!YmdHi', $bucket, 'UTC');

        if (! $periodStart instanceof CarbonImmutable) {
            return;
        }

        $this->persistMetrics($data, $periodStart);
        $this->persistClients($data['clients'], $periodStart, $topClients);
        $this->persistUnmatched($data['unmatched'], $periodStart, config()->integer('api.usage.top_unmatched'));
    }

    /**
     * Upsert the per-endpoint metric rows for a bucket.
     *
     * @param  array{requests: array<string, int>, latency: array<string, int>, histogram: array<string, int>, clients: array<string, int>, unmatched: array<string, int>}  $data
     */
    private function persistMetrics(array $data, CarbonImmutable $periodStart): void
    {
        $rows = [];

        foreach ($data['requests'] as $dimension => $count) {
            $parts = explode('|', $dimension);

            if (count($parts) !== 3) {
                continue;
            }

            [$routeName, $method, $status] = $parts;

            $row = [
                'period' => ApiUsagePeriod::Minute->value,
                'period_start' => $periodStart,
                'route_name' => $routeName,
                'method' => $method,
                'status_code' => (int) $status,
                'request_count' => $count,
                'latency_sum_ms' => $data['latency'][$dimension] ?? 0,
            ];

            foreach (ApiLatencyBucket::columns() as $column) {
                $row[$column] = $data['histogram'][$dimension.'|'.$column] ?? 0;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            return;
        }

        ApiUsageMetric::query()->upsert($rows, ['period', 'period_start', 'route_name', 'method', 'status_code']);
    }

    /**
     * Upsert the heaviest client rows for a bucket, keeping only the top-N IPs.
     *
     * @param  array<string, int>  $clients
     */
    private function persistClients(array $clients, CarbonImmutable $periodStart, int $topClients): void
    {
        if ($clients === []) {
            return;
        }

        arsort($clients);

        $rows = [];

        foreach (array_slice($clients, 0, $topClients, true) as $ip => $count) {
            $rows[] = [
                'period' => ApiUsagePeriod::Minute->value,
                'period_start' => $periodStart,
                'ip' => (string) $ip,
                'request_count' => $count,
            ];
        }

        ApiUsageClient::query()->upsert($rows, ['period', 'period_start', 'ip']);
    }

    /**
     * Upsert the busiest unmatched-path rows for a bucket, keeping only the top-N paths.
     *
     * @param  array<string, int>  $unmatched
     */
    private function persistUnmatched(array $unmatched, CarbonImmutable $periodStart, int $topUnmatched): void
    {
        if ($unmatched === []) {
            return;
        }

        arsort($unmatched);

        $rows = [];

        foreach (array_slice($unmatched, 0, $topUnmatched, true) as $dimension => $count) {
            // The path segment is last and may itself contain pipes, so cap the split at three parts.
            $parts = explode('|', (string) $dimension, 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$method, $status, $path] = $parts;

            $rows[] = [
                'period' => ApiUsagePeriod::Minute->value,
                'period_start' => $periodStart,
                'path' => $path,
                'method' => $method,
                'status_code' => (int) $status,
                'request_count' => $count,
            ];
        }

        if ($rows === []) {
            return;
        }

        ApiUsageUnmatchedRequest::query()->upsert($rows, ['period', 'period_start', 'path', 'method', 'status_code']);
    }
}
