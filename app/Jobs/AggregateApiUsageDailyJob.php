<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rolls the previous day's per-minute API usage rows up into coarse daily rows, then prunes data past its retention.
 *
 * Runs once a day. Daily rows let the dashboard show long-term trends cheaply after the fine-grained minute rows have
 * been pruned. Both the rollup and prune are idempotent... the rollup upserts with SET semantics, and the prune simply
 * deletes anything older than the configured windows.
 */
#[Timeout(300)]
#[Tries(1)]
final class AggregateApiUsageDailyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of rows to delete per statement when pruning, to keep locks short.
     */
    private const int PRUNE_CHUNK = 1000;

    public function handle(): void
    {
        $dayStart = now()->utc()->subDay()->startOfDay();

        $this->rollUpMetrics($dayStart);
        $this->rollUpClients($dayStart);
        $this->prune();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('AggregateApiUsageDailyJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Sum the previous day's minute metric rows into a single daily row per endpoint dimension.
     */
    private function rollUpMetrics(CarbonImmutable $dayStart): void
    {
        $aggregated = ApiUsageMetric::query()
            ->where('period', ApiUsagePeriod::Minute->value)
            ->whereBetween('period_start', [$dayStart, $dayStart->endOfDay()])
            ->groupBy('route_name', 'method', 'status_code')
            ->selectRaw('route_name, method, status_code, '.ApiUsageMetric::sumSelect())
            ->get();

        if ($aggregated->isEmpty()) {
            return;
        }

        $rows = $aggregated->map(static fn (ApiUsageMetric $row): array => [
            'period' => ApiUsagePeriod::Day->value,
            'period_start' => $dayStart,
            'route_name' => $row->route_name,
            'method' => $row->method,
            'status_code' => $row->status_code,
            'request_count' => $row->request_count,
            'latency_sum_ms' => $row->latency_sum_ms,
            ...$row->histogram(),
        ])->all();

        ApiUsageMetric::query()->upsert($rows, ['period', 'period_start', 'route_name', 'method', 'status_code']);
    }

    /**
     * Sum the previous day's minute client rows into the top-N daily client rows.
     */
    private function rollUpClients(CarbonImmutable $dayStart): void
    {
        $aggregated = ApiUsageClient::query()
            ->where('period', ApiUsagePeriod::Minute->value)
            ->whereBetween('period_start', [$dayStart, $dayStart->endOfDay()])
            ->groupBy('ip')
            ->selectRaw('ip, SUM(request_count) as request_count')
            ->orderByDesc('request_count')
            ->limit(config()->integer('api.usage.top_clients'))
            ->get();

        if ($aggregated->isEmpty()) {
            return;
        }

        $rows = $aggregated->map(static fn (ApiUsageClient $row): array => [
            'period' => ApiUsagePeriod::Day->value,
            'period_start' => $dayStart,
            'ip' => $row->ip,
            'request_count' => $row->request_count,
        ])->all();

        ApiUsageClient::query()->upsert($rows, ['period', 'period_start', 'ip']);
    }

    /**
     * Delete rows older than their configured retention window.
     */
    private function prune(): void
    {
        $now = now()->utc();
        $minuteCutoff = $now->subDays(config()->integer('api.usage.retention.minute_days'))->startOfDay();
        $dayCutoff = $now->subDays(config()->integer('api.usage.retention.day_days'))->startOfDay();

        foreach ([ApiUsageMetric::class, ApiUsageClient::class] as $model) {
            $this->pruneRows($model, ApiUsagePeriod::Minute, $minuteCutoff);
            $this->pruneRows($model, ApiUsagePeriod::Day, $dayCutoff);
        }
    }

    /**
     * Delete the matching rows for one model and period in bounded chunks to keep locks short.
     *
     * @param  class-string<Model>  $model
     */
    private function pruneRows(string $model, ApiUsagePeriod $period, CarbonImmutable $cutoff): void
    {
        do {
            $deleted = $model::query()
                ->where('period', $period->value)
                ->where('period_start', '<', $cutoff)
                ->limit(self::PRUNE_CHUNK)
                ->delete();
        } while ($deleted > 0);
    }
}
