<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TrackingEventType;
use App\Jobs\ComputeVisitorAnalyticsStatsJob;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use App\Support\DataTransferObjects\VisitorAnalyticsStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Computes and serves the admin visitor analytics statistics.
 *
 * Stats are never computed during a web request. The Livewire component asks this service to ensure a payload exists
 * for its filter combination; on a cache miss a queued job computes the stats and stores them as a plain array, while
 * the component polls until they appear. Cached payloads are served immediately and refreshed in the background once
 * they pass the freshness window. A small run-state cache entry tracks the queued job so duplicate dispatches are
 * suppressed and failures can be surfaced with a retry.
 */
final class VisitorAnalyticsService
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_FAILED = 'failed';

    /**
     * How long a computed stats payload stays in the cache.
     */
    private const int STATS_CACHE_SECONDS = 21600;

    /**
     * The age at which a cached payload is refreshed in the background on the next view.
     */
    private const int STATS_FRESH_SECONDS = 900;

    /**
     * How long a run-state entry lives, bounding duplicate-dispatch suppression and failure backoff.
     */
    private const int RUN_STATE_SECONDS = 600;

    /**
     * The cached stats for the given filters, or null when none have been computed yet.
     */
    public function getStats(VisitorAnalyticsFilters $filters): ?VisitorAnalyticsStats
    {
        $cached = Cache::get($filters->cacheKey());

        return is_array($cached) ? VisitorAnalyticsStats::fromArray($cached) : null;
    }

    /**
     * The state of the active or most recent stats run for the given filters, or null when none is tracked.
     *
     * @return array{status: string, error: ?string}|null
     */
    public function getRunState(VisitorAnalyticsFilters $filters): ?array
    {
        $state = Cache::get($this->runStateKey($filters));

        if (! is_array($state) || ! is_string($state['status'] ?? null)) {
            return null;
        }

        $error = $state['error'] ?? null;

        return [
            'status' => $state['status'],
            'error' => is_string($error) ? $error : null,
        ];
    }

    /**
     * Guarantee stats for the given filters exist or are being computed: queue a run on a cache miss, and queue a
     * background refresh when the cached payload has passed the freshness window.
     */
    public function ensureStatsAvailable(VisitorAnalyticsFilters $filters): void
    {
        $stats = $this->getStats($filters);

        if (! $stats instanceof VisitorAnalyticsStats || $stats->isOlderThan(self::STATS_FRESH_SECONDS)) {
            $this->queueRun($filters);
        }
    }

    /**
     * Discard any failed run state and queue a fresh run.
     */
    public function retry(VisitorAnalyticsFilters $filters): void
    {
        Cache::forget($this->runStateKey($filters));

        $this->queueRun($filters);
    }

    /**
     * Record that the queued job has started computing.
     */
    public function markProcessing(VisitorAnalyticsFilters $filters): void
    {
        Cache::put(
            $this->runStateKey($filters),
            ['status' => self::STATUS_PROCESSING, 'error' => null],
            self::RUN_STATE_SECONDS
        );
    }

    /**
     * Store a computed stats payload and clear the run state.
     */
    public function storeStats(VisitorAnalyticsFilters $filters, VisitorAnalyticsStats $stats): void
    {
        Cache::put($filters->cacheKey(), $stats->toArray(), self::STATS_CACHE_SECONDS);
        Cache::forget($this->runStateKey($filters));
    }

    /**
     * Record a failed run with its reason.
     */
    public function markFailed(VisitorAnalyticsFilters $filters, string $reason): void
    {
        Cache::put(
            $this->runStateKey($filters),
            ['status' => self::STATUS_FAILED, 'error' => $reason],
            self::RUN_STATE_SECONDS
        );
    }

    /**
     * Compute the statistics for the given filters from the tracking_events table.
     *
     * Splits the expensive COUNT(DISTINCT ip) from the other aggregates so MySQL can use the covering index
     * efficiently for each.
     */
    public function computeStats(VisitorAnalyticsFilters $filters): VisitorAnalyticsStats
    {
        $baseQuery = TrackingEvent::query();
        $this->applyFilters($baseQuery, $filters);

        $counts = (clone $baseQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated_events')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NULL THEN 1 ELSE 0 END) as anonymous_events')
            ->selectRaw('COUNT(DISTINCT country_code) as unique_countries')
            ->first();

        $uniqueUsers = (clone $baseQuery)
            ->toBase()
            ->selectRaw('COUNT(DISTINCT ip) as unique_users')
            ->value('unique_users');

        $dailyEvents = array_values((clone $baseQuery)
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
            ->all());

        return new VisitorAnalyticsStats(
            totalEvents: $this->toInt($counts->total_events ?? null),
            uniqueUsers: $this->toInt($uniqueUsers),
            authenticatedEvents: $this->toInt($counts->authenticated_events ?? null),
            anonymousEvents: $this->toInt($counts->anonymous_events ?? null),
            uniqueCountries: $this->toInt($counts->unique_countries ?? null),
            topEvents: $this->getTopEvents(clone $baseQuery),
            topBrowsers: $this->getTopBrowsers(clone $baseQuery),
            topPlatforms: $this->getTopPlatforms(clone $baseQuery),
            topCountries: $this->getTopCountries(clone $baseQuery),
            dailyEvents: $dailyEvents,
            computedAt: now()->getTimestamp(),
        );
    }

    /**
     * Apply the full filter set to a tracking events query, always bounding the date range.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    public function applyFilters(Builder $query, VisitorAnalyticsFilters $filters): void
    {
        $query->whereBetween('tracking_events.created_at', [
            $filters->effectiveDateFrom(),
            $filters->effectiveDateTo(),
        ]);

        if ($this->isActive($filters->eventName)) {
            $query->where('tracking_events.event_name', '=', $filters->eventName);
        }

        $this->applyTechnicalFilters($query, $filters);
        $this->applyGeographicFilters($query, $filters);
        $this->applyUserFilters($query, $filters);
    }

    /**
     * Queue a stats computation unless one is already queued, running, or backing off after a failure.
     */
    private function queueRun(VisitorAnalyticsFilters $filters): void
    {
        $added = Cache::add(
            $this->runStateKey($filters),
            ['status' => self::STATUS_PENDING, 'error' => null],
            self::RUN_STATE_SECONDS
        );

        if ($added) {
            dispatch(new ComputeVisitorAnalyticsStatsJob($filters));
        }
    }

    /**
     * The cache key tracking the queued run for the given filters.
     */
    private function runStateKey(VisitorAnalyticsFilters $filters): string
    {
        return $filters->cacheKey().':state';
    }

    /**
     * Apply technical filters (IP, browser, platform, device, referer) to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyTechnicalFilters(Builder $query, VisitorAnalyticsFilters $filters): void
    {
        if ($this->isActive($filters->ip)) {
            $query->where('tracking_events.ip', 'like', '%'.$filters->ip.'%');
        }

        if ($this->isActive($filters->browser)) {
            if ($filters->browser === 'Other') {
                $query->whereNotIn('tracking_events.browser', ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']);
            } else {
                $query->where('tracking_events.browser', '=', $filters->browser);
            }
        }

        if ($this->isActive($filters->platform)) {
            if ($filters->platform === 'Other') {
                $query->whereNotIn('tracking_events.platform', ['Windows', 'macOS', 'Linux', 'iOS', 'Android']);
            } else {
                $query->where('tracking_events.platform', '=', $filters->platform);
            }
        }

        if ($this->isActive($filters->device)) {
            $query->where('tracking_events.device', '=', $filters->device);
        }

        if ($this->isActive($filters->referer)) {
            $query->where('tracking_events.referer', 'like', '%'.$filters->referer.'%');
        }
    }

    /**
     * Apply geographic filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyGeographicFilters(Builder $query, VisitorAnalyticsFilters $filters): void
    {
        if ($this->isActive($filters->country)) {
            $query->where('tracking_events.country_name', 'like', '%'.$filters->country.'%');
        }

        if ($this->isActive($filters->region)) {
            $query->where('tracking_events.region_name', 'like', '%'.$filters->region.'%');
        }

        if ($this->isActive($filters->city)) {
            $query->where('tracking_events.city_name', 'like', '%'.$filters->city.'%');
        }
    }

    /**
     * Apply user-type and user-search filters to the query.
     *
     * The user search resolves matching user ids from the users table first, then constrains events to those visitor
     * ids, keeping the whole clause inside the other active filters and letting MySQL use the visitor_id index.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyUserFilters(Builder $query, VisitorAnalyticsFilters $filters): void
    {
        if ($filters->userType === 'authenticated') {
            $query->whereNotNull('tracking_events.visitor_id');
        } elseif ($filters->userType === 'anonymous') {
            $query->whereNull('tracking_events.visitor_id');
        }

        if (! $this->isActive($filters->userSearch)) {
            return;
        }

        $term = $filters->userSearch;

        $userIds = User::query()
            ->where(function (Builder $userQuery) use ($term): void {
                $userQuery->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            })
            ->pluck('id');

        if (ctype_digit($term)) {
            $userIds->push((int) $term);
        }

        $query->whereIn('tracking_events.visitor_id', $userIds->unique()->all());
    }

    /**
     * Whether a filter input holds a usable value.
     */
    private function isActive(string $value): bool
    {
        return $value !== '' && $value !== '0';
    }

    /**
     * Coerce a raw aggregate value into an integer.
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get top events' statistics.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return list<array{event_name: string, count: int}>
     */
    private function getTopEvents(Builder $query): array
    {
        $validEventNames = collect(TrackingEventType::cases())
            ->map(fn (TrackingEventType $case): string => $case->value)
            ->all();

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
}
