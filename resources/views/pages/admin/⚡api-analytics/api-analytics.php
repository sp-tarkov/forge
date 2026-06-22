<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiLatencyBucket;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
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
     * Request volume over time, one point per bucket, for the trend chart. Each point carries a height percentage
     * scaled to the busiest bucket so the view can render bars without any logic of its own.
     *
     * @return array<int, array{label: string, requests: int, percent: float}>
     */
    #[Computed]
    public function timeSeries(): array
    {
        $format = $this->period() === ApiUsagePeriod::Minute ? 'M j H:i' : 'M j';

        $points = $this->metricQuery()
            ->groupBy('period_start')
            ->selectRaw('period_start, SUM(request_count) as request_count')
            ->orderBy('period_start')
            ->get()
            ->map(static fn (ApiUsageMetric $row): array => [
                'label' => $row->period_start->format($format),
                'requests' => $row->request_count,
            ]);

        $max = (int) $points->max(static fn (array $point): int => $point['requests']);

        return $points
            ->map(static fn (array $point): array => [
                ...$point,
                'percent' => $max > 0 ? ($point['requests'] / $max) * 100 : 0.0,
            ])
            ->all();
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
     * The heaviest callers by IP over the range.
     *
     * @return array<int, array{ip: string, requests: int}>
     */
    #[Computed]
    public function topClients(): array
    {
        return ApiUsageClient::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from())
            ->groupBy('ip')
            ->selectRaw('ip, SUM(request_count) as request_count')
            ->orderByDesc('request_count')
            ->limit(config()->integer('api.usage.top_clients'))
            ->get()
            ->map(static fn (ApiUsageClient $row): array => [
                'ip' => $row->ip,
                'requests' => $row->request_count,
            ])
            ->all();
    }

    /**
     * Whether any usage data exists for the current range.
     */
    #[Computed]
    public function hasData(): bool
    {
        return $this->summary()['requests'] > 0;
    }

    /**
     * Base metric query scoped to the selected granularity and window.
     *
     * @return Builder<ApiUsageMetric>
     */
    private function metricQuery(): Builder
    {
        return ApiUsageMetric::query()
            ->where('period', $this->period()->value)
            ->where('period_start', '>=', $this->from());
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
