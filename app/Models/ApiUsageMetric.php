<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Api\V0\ApiUsagePeriod;
use Carbon\CarbonImmutable;
use Database\Factories\ApiUsageMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property ApiUsagePeriod $period
 * @property CarbonImmutable $period_start
 * @property string $route_name
 * @property string $method
 * @property int $status_code
 * @property int $request_count
 * @property int $latency_sum_ms
 * @property int $lat_b0
 * @property int $lat_b1
 * @property int $lat_b2
 * @property int $lat_b3
 * @property int $lat_b4
 * @property int $lat_b5
 * @property int $lat_b6
 * @property int $lat_b7
 * @property int $lat_b8
 * @property int $lat_b9
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ApiUsageMetric extends Model
{
    /** @use HasFactory<ApiUsageMetricFactory> */
    use HasFactory;

    /**
     * The `SUM(...) as ...` projection over every additive column, used by the rollup and dashboard aggregation queries.
     * It is a vetted literal so it can be passed to `selectRaw()` safely. The histogram columns mirror ApiLatencyBucket
     * one-to-one, and ApiUsageMetricTest asserts this projection stays in lockstep with the enum so they cannot drift.
     *
     * @return literal-string
     */
    public static function sumSelect(): string
    {
        return 'SUM(request_count) as request_count, SUM(latency_sum_ms) as latency_sum_ms, '
            .'SUM(lat_b0) as lat_b0, SUM(lat_b1) as lat_b1, SUM(lat_b2) as lat_b2, SUM(lat_b3) as lat_b3, '
            .'SUM(lat_b4) as lat_b4, SUM(lat_b5) as lat_b5, SUM(lat_b6) as lat_b6, SUM(lat_b7) as lat_b7, '
            .'SUM(lat_b8) as lat_b8, SUM(lat_b9) as lat_b9';
    }

    /**
     * The total number of API requests served in the trailing 24 hours, summed from the per-minute rollup rows. Callers
     * should cache this; it scans a day of minute buckets via the (period, period_start) index.
     */
    public static function requestsInLast24Hours(): int
    {
        return (int) self::query()
            ->where('period', ApiUsagePeriod::Minute->value)
            ->where('period_start', '>=', now()->utc()->subDay())
            ->sum('request_count');
    }

    /**
     * The mean latency in milliseconds for this row, or null when there are no requests.
     */
    public function averageLatencyMs(): ?float
    {
        return $this->request_count > 0 ? $this->latency_sum_ms / $this->request_count : null;
    }

    /**
     * The latency histogram for this row keyed by bucket column (lat_b0..lat_b9).
     *
     * @return array<string, int>
     */
    public function histogram(): array
    {
        return [
            'lat_b0' => $this->lat_b0,
            'lat_b1' => $this->lat_b1,
            'lat_b2' => $this->lat_b2,
            'lat_b3' => $this->lat_b3,
            'lat_b4' => $this->lat_b4,
            'lat_b5' => $this->lat_b5,
            'lat_b6' => $this->lat_b6,
            'lat_b7' => $this->lat_b7,
            'lat_b8' => $this->lat_b8,
            'lat_b9' => $this->lat_b9,
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'period' => ApiUsagePeriod::class,
            'period_start' => 'datetime',
            'status_code' => 'integer',
            'request_count' => 'integer',
            'latency_sum_ms' => 'integer',
            'lat_b0' => 'integer',
            'lat_b1' => 'integer',
            'lat_b2' => 'integer',
            'lat_b3' => 'integer',
            'lat_b4' => 'integer',
            'lat_b5' => 'integer',
            'lat_b6' => 'integer',
            'lat_b7' => 'integer',
            'lat_b8' => 'integer',
            'lat_b9' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
