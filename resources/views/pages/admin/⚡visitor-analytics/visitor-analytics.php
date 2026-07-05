<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Paginator as SimplePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] #[Title('Event Analytics - The Forge')] class extends Component
{
    use WithPagination;

    /**
     * The columns the events table can be sorted by.
     *
     * @var list<string>
     */
    private const array SORTABLE_COLUMNS = [
        'created_at',
        'event_name',
        'visitor_id',
        'ip',
        'browser',
        'platform',
        'device',
        'country_name',
    ];

    /**
     * The MySQL-side execution cap for the events list query, in milliseconds.
     */
    private const int LIST_QUERY_TIMEOUT_MS = 20000;

    /**
     * The MySQL error number raised when a query exceeds its MAX_EXECUTION_TIME hint.
     */
    private const int MYSQL_MAX_EXECUTION_TIME_ERRNO = 3024;

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
     * Whether the last events list query was cancelled by the database-side execution cap.
     */
    public bool $listTimedOut = false;

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
        abort_unless((bool) auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');

        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subDays(VisitorAnalyticsFilters::DEFAULT_RANGE_DAYS)->format('Y-m-d');
    }

    /**
     * Get paginated tracking events based on current filters.
     *
     * Uses simplePaginate() instead of paginate() to avoid expensive COUNT(*) query on millions of rows. The query
     * carries a MAX_EXECUTION_TIME optimizer hint so a pathological filter combination is cancelled by MySQL and
     * rendered as a friendly notice instead of hitting the request timeout.
     *
     * @return Paginator<int, TrackingEvent>
     */
    #[Computed]
    public function events(): Paginator
    {
        $this->listTimedOut = false;

        $validEventNames = collect(TrackingEventType::cases())->map(fn (TrackingEventType $case): string => $case->value)->all();

        $query = TrackingEvent::query()
            ->with(['user', 'visitable'])
            ->whereIn('event_name', $validEventNames)
            ->selectRaw(sprintf('/*+ MAX_EXECUTION_TIME(%d) */ tracking_events.id', self::LIST_QUERY_TIMEOUT_MS))
            ->addSelect([
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

        resolve(VisitorAnalyticsService::class)->applyFilters($query, $this->filters());

        $sortColumn = in_array($this->sortBy, self::SORTABLE_COLUMNS, true) ? $this->sortBy : 'created_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        try {
            return $query
                ->orderBy('tracking_events.'.$sortColumn, $direction)
                ->simplePaginate(50);
        } catch (QueryException $queryException) {
            throw_if(($queryException->errorInfo[1] ?? null) !== self::MYSQL_MAX_EXECUTION_TIME_ERRNO, $queryException);

            $this->listTimedOut = true;

            /** @var list<TrackingEvent> $emptyItems */
            $emptyItems = [];

            return new SimplePaginator($emptyItems, 50);
        }
    }

    /**
     * Get the active filters for breadcrumb display.
     *
     * @return array<int, string>
     */
    public function getActiveFilters(): array
    {
        $filters = [];
        $filterSet = $this->filters();

        // Date range, shown as the effective (defaulted and capped) range actually queried
        $filters[] = sprintf(
            '%s - %s',
            $filterSet->effectiveDateFrom()->format('M j, Y'),
            $filterSet->effectiveDateTo()->format('M j, Y')
        );

        // User type filter
        if ($this->filter === 'authenticated') {
            $filters[] = 'Authenticated users';
        } elseif ($this->filter === 'anonymous') {
            $filters[] = 'Anonymous users';
        }

        // User search
        if ($this->userSearch !== '' && $this->userSearch !== '0') {
            $filters[] = sprintf("User: '%s'", $this->userSearch);
        }

        // Event filter
        if ($this->eventFilter !== '' && $this->eventFilter !== '0') {
            $eventType = TrackingEventType::tryFrom($this->eventFilter);

            if ($eventType instanceof TrackingEventType) {
                $filters[] = sprintf("Event: '%s'", $eventType->getName());
            }
        }

        // Technical filters
        if ($this->ipFilter !== '' && $this->ipFilter !== '0') {
            $filters[] = sprintf("IP: '%s'", $this->ipFilter);
        }

        if ($this->browserFilter !== '' && $this->browserFilter !== '0') {
            $filters[] = 'Browser: '.$this->browserFilter;
        }

        if ($this->platformFilter !== '' && $this->platformFilter !== '0') {
            $filters[] = 'Platform: '.$this->platformFilter;
        }

        if ($this->deviceFilter !== '' && $this->deviceFilter !== '0') {
            $filters[] = 'Device: '.$this->deviceFilter;
        }

        // Geographic filters
        if ($this->countryFilter !== '' && $this->countryFilter !== '0') {
            $filters[] = sprintf("Country: '%s'", $this->countryFilter);
        }

        if ($this->regionFilter !== '' && $this->regionFilter !== '0') {
            $filters[] = sprintf("Region: '%s'", $this->regionFilter);
        }

        if ($this->cityFilter !== '' && $this->cityFilter !== '0') {
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
        $this->dateFrom = now()->subDays(VisitorAnalyticsFilters::DEFAULT_RANGE_DAYS)->format('Y-m-d');
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
        if (! in_array($field, self::SORTABLE_COLUMNS, true)) {
            return;
        }

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
     * Resets pagination to page 1 when filters change and refills cleared date inputs with their defaults so the
     * queried range always matches what the inputs show.
     */
    public function updated(): void
    {
        if ($this->dateFrom === null || $this->dateFrom === '') {
            $this->dateFrom = now()->subDays(VisitorAnalyticsFilters::DEFAULT_RANGE_DAYS)->format('Y-m-d');
        }

        if ($this->dateTo === null || $this->dateTo === '') {
            $this->dateTo = now()->format('Y-m-d');
        }

        $this->resetPage();
    }

    /**
     * Get the display text for an event based on its type and data.
     */
    public function getEventDisplayText(TrackingEvent $event): ?string
    {
        if ($event->event_name === null) {
            return null;
        }

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
        if ($event->visitor_id && is_array($event->event_data) && isset($event->event_data['name'])) {
            return is_string($event->event_data['name']) ? $event->event_data['name'] : null;
        }

        return null;
    }

    /**
     * Get the URL for an event if it should be displayed as a link.
     */
    public function getEventUrl(TrackingEvent $event): ?string
    {
        if ($event->event_name === null) {
            return null;
        }

        $eventType = TrackingEventType::from($event->event_name);

        // Only show URL if the event type allows it
        if (! $eventType->shouldShowUrl()) {
            return null;
        }

        return $event->event_url;
    }

    /**
     * The filter DTO for the component's current filter values.
     */
    private function filters(): VisitorAnalyticsFilters
    {
        return new VisitorAnalyticsFilters(
            userType: $this->filter,
            userSearch: $this->userSearch,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            eventName: $this->eventFilter,
            ip: $this->ipFilter,
            browser: $this->browserFilter,
            platform: $this->platformFilter,
            device: $this->deviceFilter,
            referer: $this->refererFilter,
            country: $this->countryFilter,
            region: $this->regionFilter,
            city: $this->cityFilter,
        );
    }
};
