<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Lazy-loaded stats component for Visitor Analytics.
 *
 * This component is loaded asynchronously after the main page renders,
 * allowing the page to be immediately usable while expensive stats queries run.
 *
 * Uses stale-while-revalidate caching pattern for better UX on subsequent loads.
 */
new #[Lazy] class extends Component
{
    /**
     * The maximum number of days allowed for a stats query to prevent full table scans.
     */
    private const int MAX_STATS_DAYS = 365;

    /**
     * The default number of days to query when no date range is provided.
     */
    private const int DEFAULT_STATS_DAYS = 30;

    /**
     * Filter values passed from parent component.
     */
    public string $filter = 'all';

    public string $userSearch = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $eventFilter = '';

    public string $ipFilter = '';

    public string $browserFilter = '';

    public string $platformFilter = '';

    public string $deviceFilter = '';

    public string $refererFilter = '';

    public string $countryFilter = '';

    public string $regionFilter = '';

    public string $cityFilter = '';

    /**
     * Render placeholder while loading.
     */
    public function placeholder(): string
    {
        return <<<'HTML'
        <flux:skeleton.group animate="shimmer" class="space-y-6">
            {{-- Stats Cards Skeleton --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                @for ($i = 0; $i < 5; $i++)
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div class="space-y-2">
                            <flux:skeleton class="h-3 w-20 rounded" />
                            <flux:skeleton class="h-8 w-24 rounded" />
                        </div>
                        <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
                            <flux:skeleton class="size-6 rounded" />
                        </div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Top Stats Skeleton --}}
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                @for ($i = 0; $i < 4; $i++)
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <flux:skeleton class="h-5 w-24 rounded mb-4" />
                    <div class="space-y-3">
                        @for ($j = 0; $j < 5; $j++)
                        <div class="flex justify-between items-center">
                            <flux:skeleton class="h-4 w-32 rounded" />
                            <flux:skeleton class="h-5 w-12 rounded-full" />
                        </div>
                        @endfor
                    </div>
                </div>
                @endfor
            </div>
        </flux:skeleton.group>
        HTML;
    }

    /**
     * Get analytics statistics based on all current filters.
     *
     * Uses stale-while-revalidate caching: fresh for 15 minutes, stale up to 30 minutes.
     * During the stale period, cached data is served immediately while fresh data is
     * computed in the background.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        $cacheKey = $this->getStatsCacheKey();

        // Fresh for 15 minutes, stale for up to 30 minutes
        return Cache::flexible($cacheKey, [900, 1800], fn (): array => $this->computeStats());
    }

    /**
     * Compute statistics from tracking_events table.
     *
     * Enforces a date range cap to prevent full table scans on 7M+ rows.
     * Splits the expensive COUNT(DISTINCT ip) from other aggregates so
     * MySQL can use the covering index efficiently for each.
     *
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $baseQuery = TrackingEvent::query();
        $this->applyFilters($baseQuery);

        // Lightweight aggregates that MySQL can answer from the covering index
        $counts = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated_events')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NULL THEN 1 ELSE 0 END) as anonymous_events')
            ->selectRaw('COUNT(DISTINCT country_code) as unique_countries')
            ->first();

        // COUNT(DISTINCT ip) is the heaviest aggregate — run separately so
        // MySQL can use an index-only loose scan on ip
        $uniqueUsers = (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT ip) as unique_users')
            ->value('unique_users');

        // Daily event counts for the chart — uses toBase() so the query builder
        // returns stdClass rows instead of Eloquent models, giving PHPStan clean types.
        $dailyEvents = (clone $baseQuery)
            ->toBase()
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as events')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn (stdClass $row): array => [
                'date' => is_string($row->date) ? $row->date : '',
                'events' => is_numeric($row->events) ? (int) $row->events : 0,
            ])
            ->all();

        return [
            'total_events' => (int) ($counts->total_events ?? 0), // @phpstan-ignore cast.int
            'unique_users' => (int) ($uniqueUsers ?? 0), // @phpstan-ignore cast.int
            'authenticated_events' => (int) ($counts->authenticated_events ?? 0), // @phpstan-ignore cast.int
            'anonymous_events' => (int) ($counts->anonymous_events ?? 0), // @phpstan-ignore cast.int
            'top_events' => $this->getTopEvents(clone $baseQuery),
            'top_browsers' => $this->getTopBrowsers(clone $baseQuery),
            'top_platforms' => $this->getTopPlatforms(clone $baseQuery),
            'top_countries' => $this->getTopCountries(clone $baseQuery),
            'unique_countries' => (int) ($counts->unique_countries ?? 0), // @phpstan-ignore cast.int
            'daily_events' => $dailyEvents,
        ];
    }

    /**
     * Generate a cache key for stats based on current filter values.
     *
     * Includes the effective (capped) date range so that different cap
     * calculations for the same raw filter values still hit the right cache entry.
     */
    private function getStatsCacheKey(): string
    {
        $effectiveDateTo = $this->dateTo ?? now()->format('Y-m-d');
        $effectiveDateFrom = $this->dateFrom ?? now()->subDays(self::DEFAULT_STATS_DAYS)->format('Y-m-d');

        // Apply the same cap logic used in applyDateFilters
        $maxFrom = Date::parse($effectiveDateTo)->subDays(self::MAX_STATS_DAYS)->format('Y-m-d');
        if ($effectiveDateFrom < $maxFrom) {
            $effectiveDateFrom = $maxFrom;
        }

        $filterValues = [
            'filter' => $this->filter,
            'userSearch' => $this->userSearch,
            'dateFrom' => $effectiveDateFrom,
            'dateTo' => $effectiveDateTo,
            'eventFilter' => $this->eventFilter,
            'ipFilter' => $this->ipFilter,
            'browserFilter' => $this->browserFilter,
            'platformFilter' => $this->platformFilter,
            'deviceFilter' => $this->deviceFilter,
            'refererFilter' => $this->refererFilter,
            'countryFilter' => $this->countryFilter,
            'regionFilter' => $this->regionFilter,
            'cityFilter' => $this->cityFilter,
        ];

        return 'visitor_analytics_stats:'.md5(serialize($filterValues));
    }

    /**
     * Apply all active filters to the given query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyFilters(Builder $query): void
    {
        $this->applyDateFilters($query);
        $this->applyEventFilters($query);
        $this->applyTechnicalFilters($query);
        $this->applyGeographicFilters($query);
        $this->applyUserFilters($query);
    }

    /**
     * Apply date range filters to the query.
     *
     * Enforces a bounded date range to prevent full table scans on large tables.
     * When no dates are provided, defaults to the last DEFAULT_STATS_DAYS days.
     * When the range exceeds MAX_STATS_DAYS, the start date is capped.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyDateFilters(Builder $query): void
    {
        $dateTo = $this->dateTo
            ? Date::parse($this->dateTo)->endOfDay()
            : now()->endOfDay();

        $dateFrom = $this->dateFrom
            ? Date::parse($this->dateFrom)->startOfDay()
            : now()->subDays(self::DEFAULT_STATS_DAYS)->startOfDay();

        // Cap the range to prevent scanning the entire table
        $maxFrom = $dateTo->copy()->subDays(self::MAX_STATS_DAYS)->startOfDay();
        if ($dateFrom->lt($maxFrom)) {
            $dateFrom = $maxFrom;
        }

        $query->where('tracking_events.created_at', '>=', $dateFrom);
        $query->where('tracking_events.created_at', '<=', $dateTo);
    }

    /**
     * Apply event-specific filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyEventFilters(Builder $query): void
    {
        if ($this->eventFilter !== '' && $this->eventFilter !== '0') {
            $query->where('tracking_events.event_name', '=', $this->eventFilter);
        }
    }

    /**
     * Apply technical filters (IP, browser, platform, device, referer) to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyTechnicalFilters(Builder $query): void
    {
        if ($this->ipFilter !== '' && $this->ipFilter !== '0') {
            $query->where('tracking_events.ip', 'like', '%'.$this->ipFilter.'%');
        }

        if ($this->browserFilter !== '' && $this->browserFilter !== '0') {
            if ($this->browserFilter === 'Other') {
                $query->whereNotIn('tracking_events.browser', ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']);
            } else {
                $query->where('tracking_events.browser', '=', $this->browserFilter);
            }
        }

        if ($this->platformFilter !== '' && $this->platformFilter !== '0') {
            if ($this->platformFilter === 'Other') {
                $query->whereNotIn('tracking_events.platform', ['Windows', 'macOS', 'Linux', 'iOS', 'Android']);
            } else {
                $query->where('tracking_events.platform', '=', $this->platformFilter);
            }
        }

        if ($this->deviceFilter !== '' && $this->deviceFilter !== '0') {
            $query->where('tracking_events.device', '=', $this->deviceFilter);
        }

        if ($this->refererFilter !== '' && $this->refererFilter !== '0') {
            $query->whereJsonContains('tracking_events.event_data->referer', $this->refererFilter);
        }
    }

    /**
     * Apply geographic filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyGeographicFilters(Builder $query): void
    {
        if ($this->countryFilter !== '' && $this->countryFilter !== '0') {
            $query->where('tracking_events.country_name', 'like', '%'.$this->countryFilter.'%');
        }

        if ($this->regionFilter !== '' && $this->regionFilter !== '0') {
            $query->where('tracking_events.region_name', 'like', '%'.$this->regionFilter.'%');
        }

        if ($this->cityFilter !== '' && $this->cityFilter !== '0') {
            $query->where('tracking_events.city_name', 'like', '%'.$this->cityFilter.'%');
        }
    }

    /**
     * Apply user-related filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyUserFilters(Builder $query): void
    {
        // User type filter
        if ($this->filter === 'authenticated') {
            $query->whereNotNull('tracking_events.visitor_id');
        } elseif ($this->filter === 'anonymous') {
            $query->whereNull('tracking_events.visitor_id');
        }

        // User search
        if ($this->userSearch !== '' && $this->userSearch !== '0') {
            $query
                ->whereHas('user', function (Builder $q): void {
                    $q->where('name', 'like', '%'.$this->userSearch.'%')->orWhere('email', 'like', '%'.$this->userSearch.'%');
                })
                ->orWhere('tracking_events.visitor_id', 'like', '%'.$this->userSearch.'%');
        }
    }

    /**
     * Get top events' statistics.
     *
     * Returns plain arrays instead of stdClass to ensure reliable cache serialization.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return list<array{event_name: string, count: int}>
     */
    private function getTopEvents(Builder $query): array
    {
        $validEventNames = collect(TrackingEventType::cases())->map(fn (TrackingEventType $case): string => $case->value)->all();

        $results = $query->toBase()
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->whereIn('event_name', $validEventNames)
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function (stdClass $row): array {
                /** @var array{event_name: string, count: int} $data */
                $data = ['event_name' => $row->event_name, 'count' => $row->count];

                return $data;
            })->all();

        return array_values($results);
    }

    /**
     * Get top browsers' statistics.
     *
     * Returns plain arrays instead of stdClass to ensure reliable cache serialization.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return list<array{browser: string, count: int}>
     */
    private function getTopBrowsers(Builder $query): array
    {
        $results = $query->toBase()
            ->select('browser', DB::raw('COUNT(*) as count'))
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function (stdClass $row): array {
                /** @var array{browser: string, count: int} $data */
                $data = ['browser' => $row->browser, 'count' => $row->count];

                return $data;
            })->all();

        return array_values($results);
    }

    /**
     * Get top platforms statistics.
     *
     * Returns plain arrays instead of stdClass to ensure reliable cache serialization.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return list<array{platform: string, count: int}>
     */
    private function getTopPlatforms(Builder $query): array
    {
        $results = $query->toBase()
            ->select('platform', DB::raw('COUNT(*) as count'))
            ->whereNotNull('platform')
            ->groupBy('platform')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function (stdClass $row): array {
                /** @var array{platform: string, count: int} $data */
                $data = ['platform' => $row->platform, 'count' => $row->count];

                return $data;
            })->all();

        return array_values($results);
    }

    /**
     * Get top countries' statistics.
     *
     * Returns plain arrays instead of stdClass to ensure reliable cache serialization.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return list<array{country_name: string, country_code: string, count: int}>
     */
    private function getTopCountries(Builder $query): array
    {
        $results = $query->toBase()
            ->select('country_name', 'country_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('country_code')
            ->groupBy('country_name', 'country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function (stdClass $row): array {
                /** @var array{country_name: string, country_code: string, count: int} $data */
                $data = ['country_name' => $row->country_name, 'country_code' => $row->country_code, 'count' => $row->count];

                return $data;
            })->all();

        return array_values($results);
    }
};
