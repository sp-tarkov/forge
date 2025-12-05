<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
#[Lazy]
class VisitorAnalyticsStats extends Component
{
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
    public function getStats(): array
    {
        $cacheKey = $this->getStatsCacheKey();

        // Fresh for 15 minutes, stale for up to 30 minutes
        return Cache::flexible($cacheKey, [900, 1800], fn (): array => $this->computeStats());
    }

    public function render(): View
    {
        return view('livewire.admin.visitor-analytics-stats', [
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * Compute statistics from tracking_events table.
     *
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $baseQuery = TrackingEvent::query();
        $this->applyFilters($baseQuery);

        // Use a single query with conditional aggregates to reduce database round trips
        $counts = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw('COUNT(DISTINCT ip) as unique_users')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated_events')
            ->selectRaw('SUM(CASE WHEN visitor_id IS NULL THEN 1 ELSE 0 END) as anonymous_events')
            ->selectRaw('COUNT(DISTINCT country_code) as unique_countries')
            ->first();

        return [
            'total_events' => (int) ($counts->total_events ?? 0),
            'unique_users' => (int) ($counts->unique_users ?? 0),
            'authenticated_events' => (int) ($counts->authenticated_events ?? 0),
            'anonymous_events' => (int) ($counts->anonymous_events ?? 0),
            'top_events' => $this->getTopEvents(clone $baseQuery),
            'top_browsers' => $this->getTopBrowsers(clone $baseQuery),
            'top_platforms' => $this->getTopPlatforms(clone $baseQuery),
            'top_countries' => $this->getTopCountries(clone $baseQuery),
            'unique_countries' => (int) ($counts->unique_countries ?? 0),
        ];
    }

    /**
     * Generate a cache key for stats based on current filter values.
     */
    private function getStatsCacheKey(): string
    {
        $filterValues = [
            'filter' => $this->filter,
            'userSearch' => $this->userSearch,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
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
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyDateFilters(Builder $query): void
    {
        if ($this->dateFrom) {
            $query->where('tracking_events.created_at', '>=', $this->dateFrom.' 00:00:00');
        }

        if ($this->dateTo) {
            $query->where('tracking_events.created_at', '<=', $this->dateTo.' 23:59:59');
        }
    }

    /**
     * Apply event-specific filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyEventFilters(Builder $query): void
    {
        if (! empty($this->eventFilter)) {
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
        if (! empty($this->ipFilter)) {
            $query->where('tracking_events.ip', 'like', '%'.$this->ipFilter.'%');
        }

        if (! empty($this->browserFilter)) {
            if ($this->browserFilter === 'Other') {
                $query->whereNotIn('tracking_events.browser', ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']);
            } else {
                $query->where('tracking_events.browser', '=', $this->browserFilter);
            }
        }

        if (! empty($this->platformFilter)) {
            if ($this->platformFilter === 'Other') {
                $query->whereNotIn('tracking_events.platform', ['Windows', 'macOS', 'Linux', 'iOS', 'Android']);
            } else {
                $query->where('tracking_events.platform', '=', $this->platformFilter);
            }
        }

        if (! empty($this->deviceFilter)) {
            $query->where('tracking_events.device', '=', $this->deviceFilter);
        }

        if (! empty($this->refererFilter)) {
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
        if (! empty($this->countryFilter)) {
            $query->where('tracking_events.country_name', 'like', '%'.$this->countryFilter.'%');
        }

        if (! empty($this->regionFilter)) {
            $query->where('tracking_events.region_name', 'like', '%'.$this->regionFilter.'%');
        }

        if (! empty($this->cityFilter)) {
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
        if (! empty($this->userSearch)) {
            $query->whereHas('user', function (Builder $q): void {
                $q->where('name', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->userSearch.'%');
            })->orWhere('tracking_events.visitor_id', 'like', '%'.$this->userSearch.'%');
        }
    }

    /**
     * Get top events' statistics.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return Collection<int, TrackingEvent>
     */
    private function getTopEvents(Builder $query): Collection
    {
        $validEventNames = collect(TrackingEventType::cases())->map(fn (TrackingEventType $case): string => $case->value)->all();

        return $query
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->whereIn('event_name', $validEventNames)
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    /**
     * Get top browsers' statistics.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return Collection<int, TrackingEvent>
     */
    private function getTopBrowsers(Builder $query): Collection
    {
        return $query
            ->select('browser', DB::raw('COUNT(*) as count'))
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    /**
     * Get top platforms statistics.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return Collection<int, TrackingEvent>
     */
    private function getTopPlatforms(Builder $query): Collection
    {
        return $query
            ->select('platform', DB::raw('COUNT(*) as count'))
            ->whereNotNull('platform')
            ->groupBy('platform')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    /**
     * Get top countries' statistics.
     *
     * @param  Builder<TrackingEvent>  $query
     * @return Collection<int, TrackingEvent>
     */
    private function getTopCountries(Builder $query): Collection
    {
        return $query
            ->select('country_name', 'country_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('country_code')
            ->groupBy('country_name', 'country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }
}
