<?php

declare(strict_types=1);

use App\Contracts\Geolocator;
use App\Enums\Api\V0\ApiLatencyBucket;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Http\Middleware\RecordApiUsage;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use App\Models\ApiUsageUnmatchedRequest;
use App\Services\GeolocationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::base')] #[Title('API Analytics - The Forge')] class extends Component
{
    /**
     * The ranges the dashboard offers, mapped to a human label.
     *
     * @var array<string, string>
     */
    public const array RANGES = [
        '24h' => 'Last 24 hours',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
    ];

    /**
     * The selected time range. Drives both which rollup granularity is read and how far back to look.
     */
    #[Url]
    public string $range = '24h';

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');

        $this->normalizeRange();
    }

    /**
     * Keep the range within the supported set whenever it changes via the URL or the selector.
     */
    public function updatedRange(): void
    {
        $this->normalizeRange();
    }

    /**
     * Per-endpoint usage aggregated over the selected range, ordered by request volume.
     *
     * Rows are aggregated by (route_name, status_code) in SQL and then collapsed per endpoint in PHP, which keeps the
     * status breakdown without any raw CASE expressions and lets every value stay typed via the model casts.
     *
     * @return array<int, array{route_name: string, requests: int, latency_sum_ms: int, histogram: array<string, int>, errors_4xx: int, errors_5xx: int, throttled: int, error_rate: float, avg_latency_ms: int|null, p95_latency_ms: int|null}>
     */
    #[Computed]
    public function endpoints(): array
    {
        $rows = $this->metricQuery()
            ->groupBy('route_name', 'status_code')
            ->selectRaw('route_name, status_code, '.ApiUsageMetric::sumSelect())
            ->get();

        $byRoute = [];

        foreach ($rows as $row) {
            $byRoute[$row->route_name] ??= [
                'route_name' => $row->route_name,
                'requests' => 0,
                'latency_sum_ms' => 0,
                'histogram' => array_fill_keys(ApiLatencyBucket::columns(), 0),
                'errors_4xx' => 0,
                'errors_5xx' => 0,
                'throttled' => 0,
            ];

            $byRoute[$row->route_name]['requests'] += $row->request_count;
            $byRoute[$row->route_name]['latency_sum_ms'] += $row->latency_sum_ms;

            foreach ($row->histogram() as $column => $count) {
                $byRoute[$row->route_name]['histogram'][$column] += $count;
            }

            if ($row->status_code === 429) {
                $byRoute[$row->route_name]['throttled'] += $row->request_count;
            }

            if ($row->status_code >= 500) {
                $byRoute[$row->route_name]['errors_5xx'] += $row->request_count;
            } elseif ($row->status_code >= 400) {
                $byRoute[$row->route_name]['errors_4xx'] += $row->request_count;
            }
        }

        $endpoints = array_map(static function (array $endpoint): array {
            $errors = $endpoint['errors_4xx'] + $endpoint['errors_5xx'];

            return [
                ...$endpoint,
                'error_rate' => $endpoint['requests'] > 0 ? ($errors / $endpoint['requests']) * 100 : 0.0,
                'avg_latency_ms' => $endpoint['requests'] > 0 ? (int) round($endpoint['latency_sum_ms'] / $endpoint['requests']) : null,
                'p95_latency_ms' => ApiLatencyBucket::estimatePercentileMs($endpoint['histogram'], $endpoint['requests'], 95),
            ];
        }, array_values($byRoute));

        usort($endpoints, static fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        return $endpoints;
    }

    /**
     * Headline totals across every endpoint in the range.
     *
     * @return array{requests: int, errors: int, error_rate: float, avg_latency_ms: int|null, p95_latency_ms: int|null}
     */
    #[Computed]
    public function summary(): array
    {
        $endpoints = collect($this->endpoints());

        $requests = (int) $endpoints->sum(static fn (array $endpoint): int => $endpoint['requests']);
        $errors = (int) $endpoints->sum(static fn (array $endpoint): int => $endpoint['errors_4xx'] + $endpoint['errors_5xx']);
        $latencySum = (int) $endpoints->sum(static fn (array $endpoint): int => $endpoint['latency_sum_ms']);

        $histogram = [];

        foreach (ApiLatencyBucket::columns() as $column) {
            $histogram[$column] = (int) $endpoints->sum(static fn (array $endpoint): int => $endpoint['histogram'][$column] ?? 0);
        }

        return [
            'requests' => $requests,
            'errors' => $errors,
            'error_rate' => $requests > 0 ? ($errors / $requests) * 100 : 0.0,
            'avg_latency_ms' => $requests > 0 ? (int) round($latencySum / $requests) : null,
            'p95_latency_ms' => ApiLatencyBucket::estimatePercentileMs($histogram, $requests, 95),
        ];
    }

    /**
     * Request volume over time for the trend chart. Storage rows are summed into fixed display buckets (15 minutes
     * for the 24h range, one day otherwise) and every bucket in the range is emitted, so gaps render as zero-height
     * bars instead of collapsing the axis. Each point carries a height percentage scaled to the busiest bucket so the
     * view can render bars without any logic of its own.
     *
     * @return array<int, array{label: string, requests: int, percent: float}>
     */
    #[Computed]
    public function timeSeries(): array
    {
        $step = $this->chartBucketSeconds();
        $format = $this->period() === ApiUsagePeriod::Minute ? 'M j H:i' : 'M j';

        $buckets = [];
        $start = intdiv($this->from()->getTimestamp(), $step) * $step;
        $end = intdiv($this->chartEnd()->getTimestamp(), $step) * $step;

        for ($timestamp = $start; $timestamp <= $end; $timestamp += $step) {
            $buckets[$timestamp] = 0;
        }

        $rows = $this->metricQuery()
            ->groupBy('period_start')
            ->selectRaw('period_start, SUM(request_count) as request_count')
            ->get();

        foreach ($rows as $row) {
            $timestamp = intdiv($row->period_start->getTimestamp(), $step) * $step;

            if (array_key_exists($timestamp, $buckets)) {
                $buckets[$timestamp] += $row->request_count;
            }
        }

        $max = $buckets === [] ? 0 : max($buckets);

        $points = [];

        foreach ($buckets as $timestamp => $requests) {
            $points[] = [
                'label' => CarbonImmutable::createFromTimestampUTC($timestamp)->format($format),
                'requests' => $requests,
                'percent' => $max > 0 ? ($requests / $max) * 100 : 0.0,
            ];
        }

        return $points;
    }

    /**
     * The busiest single bucket in the current range. Drives the volume chart's vertical scale so the axis labels and
     * bar heights share one reference point.
     */
    #[Computed]
    public function peakRequests(): int
    {
        $max = collect($this->timeSeries())->max(static fn (array $point): int => $point['requests']);

        return is_int($max) ? $max : 0;
    }

    /**
     * The heaviest callers by IP over the range, enriched with GeoIP location, their share of all origin requests,
     * how many rollup buckets they were active in, and when they were last seen.
     *
     * @return array<int, array{ip: string, requests: int, share: float, active_periods: int, last_seen: CarbonImmutable, flag: string, country_name: string|null, city_name: string|null}>
     */
    #[Computed]
    public function topClients(): array
    {
        $geolocator = resolve(Geolocator::class);
        $total = $this->summary()['requests'] + $this->unmatchedTotal();

        return ApiUsageClient::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from())
            ->groupBy('ip')
            ->selectRaw('ip, SUM(request_count) as request_count, COUNT(DISTINCT period_start) as active_periods, MAX(period_start) as last_seen')
            ->orderByDesc('request_count')
            ->limit(config()->integer('api.usage.top_clients'))
            ->toBase()
            ->get()
            ->map(static function (stdClass $row) use ($geolocator, $total): array {
                $ip = is_string($row->ip) ? $row->ip : '';
                $requests = is_numeric($row->request_count) ? (int) $row->request_count : 0;

                $location = $geolocator->getLocationFromIP($ip);
                $countryCode = $location['country_code'];
                $countryName = $location['country_name'];
                $cityName = $location['city_name'];

                return [
                    'ip' => $ip,
                    'requests' => $requests,
                    'share' => $total > 0 ? ($requests / $total) * 100 : 0.0,
                    'active_periods' => is_numeric($row->active_periods) ? (int) $row->active_periods : 0,
                    'last_seen' => is_string($row->last_seen) ? CarbonImmutable::parse($row->last_seen, 'UTC') : now()->utc(),
                    'flag' => is_string($countryCode) ? GeolocationService::getCountryFlag($countryCode) : '',
                    'country_name' => is_string($countryName) ? $countryName : null,
                    'city_name' => is_string($cityName) ? $cityName : null,
                ];
            })
            ->all();
    }

    /**
     * The total number of requests in the range that matched no registered route, from the authoritative metric rows.
     */
    #[Computed]
    public function unmatchedTotal(): int
    {
        return (int) ApiUsageMetric::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from())
            ->where('route_name', RecordApiUsage::UNMATCHED_ROUTE)
            ->sum('request_count');
    }

    /**
     * The paths callers requested that matched no registered route, aggregated over the range and ordered by volume.
     *
     * @return array<int, array{path: string, method: string, status_code: int, requests: int, last_seen: CarbonImmutable}>
     */
    #[Computed]
    public function unmatchedRequests(): array
    {
        return ApiUsageUnmatchedRequest::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from())
            ->groupBy('path', 'method', 'status_code')
            ->selectRaw('path, method, status_code, SUM(request_count) as request_count, MAX(period_start) as last_seen')
            ->orderByDesc('request_count')
            ->limit(config()->integer('api.usage.top_unmatched'))
            ->toBase()
            ->get()
            ->map(static fn (stdClass $row): array => [
                'path' => is_string($row->path) ? $row->path : '',
                'method' => is_string($row->method) ? $row->method : '',
                'status_code' => is_numeric($row->status_code) ? (int) $row->status_code : 0,
                'requests' => is_numeric($row->request_count) ? (int) $row->request_count : 0,
                'last_seen' => is_string($row->last_seen) ? CarbonImmutable::parse($row->last_seen, 'UTC') : now()->utc(),
            ])
            ->all();
    }

    /**
     * Whether any usage data exists for the current range, counting both matched and unmatched traffic.
     */
    #[Computed]
    public function hasData(): bool
    {
        return $this->summary()['requests'] > 0 || $this->unmatchedTotal() > 0;
    }

    /**
     * Base metric query scoped to the selected granularity and window. Requests that matched no route are excluded;
     * they are surfaced separately in the unmatched section.
     *
     * @return Builder<ApiUsageMetric>
     */
    private function metricQuery(): Builder
    {
        return ApiUsageMetric::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from())
            ->where('route_name', '!=', RecordApiUsage::UNMATCHED_ROUTE);
    }

    /**
     * The rollup granularity to read for the selected range. The 24h view reads fine-grained minute rows; longer
     * ranges read the coarse daily rollups (which cover completed days).
     */
    private function period(): ApiUsagePeriod
    {
        return $this->range === '24h' ? ApiUsagePeriod::Minute : ApiUsagePeriod::Day;
    }

    /**
     * The width of one trend chart bar. Minute rows are summed into 15-minute bars; day rows are one bar per day.
     */
    private function chartBucketSeconds(): int
    {
        return $this->period() === ApiUsagePeriod::Minute ? 900 : 86400;
    }

    /**
     * The end of the trend chart's time axis. Minute data runs to the current moment; daily rollups only exist for
     * completed days, so those ranges end at yesterday to avoid a permanently empty bar for today.
     */
    private function chartEnd(): CarbonImmutable
    {
        return $this->period() === ApiUsagePeriod::Minute
            ? now()->utc()
            : now()->utc()->subDay()->startOfDay();
    }

    /**
     * The earliest bucket to include for the selected range.
     */
    private function from(): CarbonImmutable
    {
        return match ($this->range) {
            '7d' => now()->utc()->subDays(7)->startOfDay(),
            '30d' => now()->utc()->subDays(30)->startOfDay(),
            '90d' => now()->utc()->subDays(90)->startOfDay(),
            default => now()->utc()->subDay(),
        };
    }

    /**
     * Collapse any unsupported range value back to the default.
     */
    private function normalizeRange(): void
    {
        if (! array_key_exists($this->range, self::RANGES)) {
            $this->range = '24h';
        }
    }
};
