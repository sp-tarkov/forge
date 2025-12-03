<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VisitorAnalytics extends Component
{
    use WithPagination;

    /**
     * User-based filters.
     */
    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $userSearch = '';

    /**
     * Date range filters.
     */
    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateTo = null;

    /**
     * Event-specific filters.
     */
    #[Url]
    public string $eventFilter = '';

    /**
     * Technical filters.
     */
    #[Url]
    public string $ipFilter = '';

    #[Url]
    public string $browserFilter = '';

    #[Url]
    public string $platformFilter = '';

    #[Url]
    public string $deviceFilter = '';

    #[Url]
    public string $refererFilter = '';

    /**
     * Geographic filters.
     */
    #[Url]
    public string $countryFilter = '';

    #[Url]
    public string $regionFilter = '';

    #[Url]
    public string $cityFilter = '';

    /**
     * Sorting configuration.
     */
    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    /**
     * Modal state for viewing event details.
     */
    public bool $showEventModal = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $selectedEvent = null;

    /**
     * Livewire lifecycle method: Initialize the component and set default values.
     *
     * This magic method is automatically called when the component is first mounted.
     * It runs once before the initial render.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Administrator privileges required.');

        // Set default date range (last month)
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subMonth()->format('Y-m-d');
    }

    /**
     * Get paginated tracking events based on current filters.
     *
     * Uses simplePaginate() instead of paginate() to avoid expensive COUNT(*) query
     * on millions of rows. This trades "total results" display for much faster queries.
     *
     * @return Paginator<int, TrackingEvent>
     */
    #[Computed]
    public function events(): Paginator
    {
        $validEventNames = collect(TrackingEventType::cases())->map(fn (TrackingEventType $case): string => $case->value)->all();

        $query = TrackingEvent::query()
            ->with(['user', 'visitable'])
            ->whereIn('event_name', $validEventNames)
            ->select([
                'tracking_events.id',
                'tracking_events.event_name',
                'tracking_events.event_data',
                'tracking_events.visitor_id',
                'tracking_events.visitable_type',
                'tracking_events.visitable_id',
                'tracking_events.url',
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
            ->simplePaginate(50);
    }

    /**
     * Get the active filters for breadcrumb display.
     *
     * @return array<int, string>
     */
    public function getActiveFilters(): array
    {
        $filters = [];

        // Date range
        if ($this->dateFrom && $this->dateTo) {
            $fromDate = Date::parse($this->dateFrom)->format('M j, Y');
            $toDate = Date::parse($this->dateTo)->format('M j, Y');
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
            $eventType = TrackingEventType::from($this->eventFilter);
            $filters[] = sprintf("Event: '%s'", $eventType->getName());
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
     * Reset all filters to their default values.
     */
    public function resetFilters(): void
    {
        $this->filter = 'all';
        $this->userSearch = '';
        $this->dateFrom = now()->subMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->eventFilter = '';
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
    public function sortByColumn(string $field): void
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
            default => null,
        };

        $this->resetPage();
    }

    /**
     * Show event details in modal.
     */
    public function showEventDetails(int $eventId): void
    {
        $event = TrackingEvent::with(['user', 'visitable'])->findOrFail($eventId);

        // Convert the event to an array with all its data
        $this->selectedEvent = [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_display_name' => $event->event_display_name,
            'event_context' => $event->event_context,
            'event_data' => $event->event_data,
            'method' => $event->event_data['method'] ?? null,
            'request' => $event->event_data['request'] ?? null,
            'url' => $event->url,
            'referer' => $event->referer,
            'languages' => $event->languages,
            'useragent' => $event->useragent,
            'headers' => $event->event_data['headers'] ?? null,
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
            'visitable' => $event->visitable !== null ? [
                'type' => $event->visitable::class,
                'id' => $event->visitable->getKey(),
                'data' => $event->visitable->toArray(),
            ] : null,
        ];

        $this->showEventModal = true;
    }

    /**
     * Livewire lifecycle method: Called after any component property is updated.
     *
     * This magic method is automatically triggered by Livewire whenever any property
     * with wire:model is updated. It ensures pagination resets to page 1 when filters
     * change, preventing "no results" confusion when the current page doesn't exist
     * in the filtered dataset.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Get the display text for an event based on its type and data.
     */
    public function getEventDisplayText(TrackingEvent $event): ?string
    {
        $eventType = TrackingEventType::from($event->event_name);

        if (! $eventType->shouldShowContext()) {
            return null;
        }

        return $event->event_context;
    }

    /**
     * Get the user model associated with an event for display purposes.
     */
    public function getEventDisplayUser(TrackingEvent $event): ?User
    {
        // Simply check if we have a user from visitor_id
        if ($event->visitor_id && $event->user) {
            return $event->user;
        }

        return null;
    }

    /**
     * Get the user ID associated with an event for display purposes.
     */
    public function getEventUserId(TrackingEvent $event): ?int
    {
        // Simply return the visitor_id
        return $event->visitor_id;
    }

    /**
     * Get the user name for display, falling back to snapshot data if the user is deleted.
     */
    public function getEventDisplayName(TrackingEvent $event): ?string
    {
        // If we have the user model, return their name
        if ($event->visitor_id && $event->user) {
            return $event->user->name;
        }

        // Fallback to snapshot data from event_data (for deleted users)
        if ($event->visitor_id && isset($event->event_data['name'])) {
            return $event->event_data['name'];
        }

        return null;
    }

    /**
     * Get the URL for an event if it should be displayed as a link.
     */
    public function getEventUrl(TrackingEvent $event): ?string
    {
        $eventType = TrackingEventType::from($event->event_name);

        // Only show URL if the event type allows it
        if (! $eventType->shouldShowUrl()) {
            return null;
        }

        return $event->event_url;
    }

    /**
     * Livewire lifecycle method: Render the component view.
     *
     * This magic method is automatically called by Livewire to generate the component's
     * HTML output. It's called on initial load and after any property updates or actions.
     */
    public function render(): View
    {
        return view('livewire.admin.visitor-analytics')
            ->layout('components.layouts.base', [
                'title' => 'Event Analytics - The Forge',
                'description' => 'View detailed event analytics and user activity statistics.',
            ]);
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
}
