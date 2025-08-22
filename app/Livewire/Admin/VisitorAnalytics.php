<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\TrackingEvent;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class VisitorAnalytics extends Component
{
    use WithPagination;

    /**
     * User-based filters.
     */
    public string $filter = 'all';

    public string $userSearch = '';

    /**
     * Date range filters.
     */
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /**
     * Event-specific filters.
     */
    public string $eventFilter = '';

    public string $eventMatchType = 'contains';

    /**
     * Technical filters.
     */
    public string $ipFilter = '';

    public string $browserFilter = '';

    public string $platformFilter = '';

    public string $deviceFilter = '';

    public string $refererFilter = '';

    /**
     * Geographic filters.
     */
    public string $countryFilter = '';

    public string $regionFilter = '';

    public string $cityFilter = '';

    /**
     * Sorting configuration.
     */
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    /**
     * Modal state for viewing event details.
     */
    public bool $showEventModal = false;

    public ?array $selectedEvent = null;

    /**
     * Initialize the component and set default values.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Administrator privileges required.');

        // Set default date range (last 12 months)
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subMonths(12)->format('Y-m-d');
    }

    /**
     * Get paginated tracking events based on current filters.
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $query = TrackingEvent::query()
            ->with(['user', 'trackable'])
            ->select([
                'tracking_events.id',
                'tracking_events.event_name',
                'tracking_events.event_data',
                'tracking_events.visitor_id',
                'tracking_events.visitable_type',
                'tracking_events.visitable_id',
                'tracking_events.ip',
                'tracking_events.browser',
                'tracking_events.platform',
                'tracking_events.device',
                'tracking_events.country_code',
                'tracking_events.country_name',
                'tracking_events.region_name',
                'tracking_events.city_name',
                'tracking_events.latitude',
                'tracking_events.longitude',
                'tracking_events.timezone',
                'tracking_events.created_at',
            ]);

        $this->applyFilters($query);

        return $query
            ->orderBy('tracking_events.'.$this->sortBy, $this->sortDirection)
            ->paginate(50);
    }

    /**
     * Get the active filters for breadcrumb display.
     */
    public function getActiveFilters(): array
    {
        $filters = [];

        // Date range
        if ($this->dateFrom && $this->dateTo) {
            $fromDate = Carbon::parse($this->dateFrom)->format('M j, Y');
            $toDate = Carbon::parse($this->dateTo)->format('M j, Y');
            $filters[] = sprintf('%s - %s', $fromDate, $toDate);
        }

        // User type filter
        if ($this->filter === 'authenticated') {
            $filters[] = 'Authenticated users';
        } elseif ($this->filter === 'anonymous') {
            $filters[] = 'Anonymous users';
        }

        // User search
        if (! empty($this->userSearch)) {
            $filters[] = sprintf("User: '%s'", $this->userSearch);
        }

        // Event filter
        if (! empty($this->eventFilter)) {
            $matchType = $this->eventMatchType === 'exact' ? 'exact' : 'contains';
            $filters[] = sprintf("Event %s: '%s'", $matchType, $this->eventFilter);
        }

        // Technical filters
        if (! empty($this->ipFilter)) {
            $filters[] = sprintf("IP: '%s'", $this->ipFilter);
        }

        if (! empty($this->browserFilter)) {
            $filters[] = 'Browser: '.$this->browserFilter;
        }

        if (! empty($this->platformFilter)) {
            $filters[] = 'Platform: '.$this->platformFilter;
        }

        if (! empty($this->deviceFilter)) {
            $filters[] = 'Device: '.$this->deviceFilter;
        }

        // Geographic filters
        if (! empty($this->countryFilter)) {
            $filters[] = sprintf("Country: '%s'", $this->countryFilter);
        }

        if (! empty($this->regionFilter)) {
            $filters[] = sprintf("Region: '%s'", $this->regionFilter);
        }

        if (! empty($this->cityFilter)) {
            $filters[] = sprintf("City: '%s'", $this->cityFilter);
        }

        return $filters;
    }

    /**
     * Get analytics statistics based on all current filters.
     */
    public function getStats(): array
    {
        $baseQuery = TrackingEvent::query();

        // Apply all filters for stats (not just date filters)
        $this->applyFilters($baseQuery);

        return [
            'total_events' => (clone $baseQuery)->count(),
            'unique_users' => (clone $baseQuery)->distinct('ip')->count('ip'),
            'authenticated_events' => (clone $baseQuery)->whereNotNull('visitor_id')->count(),
            'anonymous_events' => (clone $baseQuery)->whereNull('visitor_id')->count(),
            'top_events' => $this->getTopEvents(clone $baseQuery),
            'top_browsers' => $this->getTopBrowsers(clone $baseQuery),
            'top_platforms' => $this->getTopPlatforms(clone $baseQuery),
            'top_countries' => $this->getTopCountries(clone $baseQuery),
            'unique_countries' => (clone $baseQuery)
                ->whereNotNull('country_code')
                ->distinct('country_code')
                ->count('country_code'),
        ];
    }

    /**
     * Reset all filters to their default values.
     */
    public function resetFilters(): void
    {
        $this->filter = 'all';
        $this->userSearch = '';
        $this->dateFrom = now()->subMonths(12)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->eventFilter = '';
        $this->eventMatchType = 'contains';
        $this->ipFilter = '';
        $this->browserFilter = '';
        $this->platformFilter = '';
        $this->deviceFilter = '';
        $this->refererFilter = '';
        $this->countryFilter = '';
        $this->regionFilter = '';
        $this->cityFilter = '';
        $this->resetPage();
    }

    /**
     * Toggle sorting by the specified field.
     * If already sorting by this field, toggle a direction. Otherwise, sort desc by this field.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Set a geographic filter and refresh the page.
     */
    public function setGeographicFilter(string $type, string $value): void
    {
        match ($type) {
            'country' => $this->countryFilter = $value,
            'region' => $this->regionFilter = $value,
            'city' => $this->cityFilter = $value,
        };

        $this->resetPage();
    }

    /**
     * Show event details in modal.
     */
    public function showEventDetails(int $eventId): void
    {
        $event = TrackingEvent::with(['user', 'trackable'])->findOrFail($eventId);

        // Convert the event to an array with all its data
        $this->selectedEvent = [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_display_name' => $event->event_display_name,
            'event_context' => $event->event_context,
            'event_data' => $event->event_data,
            'method' => $event->method,
            'request' => $event->request,
            'url' => $event->url,
            'referer' => $event->referer,
            'languages' => $event->languages,
            'useragent' => $event->useragent,
            'headers' => $event->headers,
            'device' => $event->device,
            'platform' => $event->platform,
            'browser' => $event->browser,
            'ip' => $event->ip,
            'visitor_id' => $event->visitor_id,
            'visitor_type' => $event->visitor_type,
            'visitable_id' => $event->visitable_id,
            'visitable_type' => $event->visitable_type,
            'country_code' => $event->country_code,
            'country_name' => $event->country_name,
            'region_name' => $event->region_name,
            'city_name' => $event->city_name,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'timezone' => $event->timezone,
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
            'user' => $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ] : null,
            'trackable' => $event->trackable ? [
                'type' => $event->trackable::class,
                'id' => $event->trackable->id,
                'data' => $event->trackable->toArray(),
            ] : null,
        ];

        $this->showEventModal = true;
    }

    /**
     * Reset pagination when any filter property is updated.
     * This is a catch-all for all the updated* methods that were previously defined.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Apply all active filters to the given query.
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
     */
    private function applyEventFilters(Builder $query): void
    {
        if (! empty($this->eventFilter)) {
            if ($this->eventMatchType === 'exact') {
                $query->where('tracking_events.event_name', '=', $this->eventFilter);
            } else {
                $query->where('tracking_events.event_name', 'like', '%'.$this->eventFilter.'%');
            }
        }
    }

    /**
     * Apply technical filters (IP, browser, platform, device, referer) to the query.
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
            $query->whereHas('user', function ($q): void {
                $q->where('name', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->userSearch.'%');
            })->orWhere('tracking_events.visitor_id', 'like', '%'.$this->userSearch.'%');
        }
    }

    /**
     * Get top events statistics.
     */
    private function getTopEvents(Builder $query): Collection
    {
        return $query
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    /**
     * Get top browsers statistics.
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
     * Get top countries statistics.
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

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.admin.visitor-analytics', [
            'events' => $this->events,
            'stats' => $this->getStats(),
        ])->layout('components.layouts.base', [
            'title' => 'Event Analytics - The Forge',
            'description' => 'View detailed event analytics and user activity statistics for The Forge.',
        ]);
    }
}
