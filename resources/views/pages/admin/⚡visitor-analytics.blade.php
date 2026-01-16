<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
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
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');

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
};
?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
                    {{ __('Visitor Analytics') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Filtering:</span>
                    <div class="min-w-0">
                        <flux:breadcrumbs class="inline-flex flex-wrap">
                            @foreach ($this->getActiveFilters() as $index => $filter)
                                <flux:breadcrumbs.item separator="slash">{{ $filter }}</flux:breadcrumbs.item>
                            @endforeach
                        </flux:breadcrumbs>
                    </div>
                </div>
            </div>
        @endif
        <div class="space-y-6">

            {{-- Lazy-loaded Stats Section --}}
            <livewire:admin.visitor-analytics-stats
                :filter="$filter"
                :user-search="$userSearch"
                :date-from="$dateFrom"
                :date-to="$dateTo"
                :event-filter="$eventFilter"
                :ip-filter="$ipFilter"
                :browser-filter="$browserFilter"
                :platform-filter="$platformFilter"
                :device-filter="$deviceFilter"
                :referer-filter="$refererFilter"
                :country-filter="$countryFilter"
                :region-filter="$regionFilter"
                :city-filter="$cityFilter"
                :key="$filter .
                    $userSearch .
                    $dateFrom .
                    $dateTo .
                    $eventFilter .
                    $ipFilter .
                    $browserFilter .
                    $platformFilter .
                    $deviceFilter .
                    $refererFilter .
                    $countryFilter .
                    $regionFilter .
                    $cityFilter"
            />

            {{-- Filters Section --}}
            <div
                id="filters-container"
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {{-- Date Range Filter --}}
                    <div>
                        <flux:label
                            for="dateFrom"
                            class="text-xs"
                        >Date From</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="dateFrom"
                            id="dateFrom"
                            size="sm"
                        />
                    </div>

                    <div>
                        <flux:label
                            for="dateTo"
                            class="text-xs"
                        >Date To</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="dateTo"
                            id="dateTo"
                            size="sm"
                        />
                    </div>

                    {{-- Event Filter --}}
                    <div>
                        <flux:label
                            for="eventFilter"
                            class="text-xs"
                        >Event</flux:label>
                        <flux:select
                            wire:model.live="eventFilter"
                            id="eventFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Events</flux:select.option>
                            @foreach (\App\Enums\TrackingEventType::cases() as $eventType)
                                <flux:select.option value="{{ $eventType->value }}">
                                    {{ $eventType->getName() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- IP Address Filter --}}
                    <div>
                        <flux:label
                            for="ipFilter"
                            class="text-xs"
                        >IP Address</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="ipFilter"
                            id="ipFilter"
                            placeholder="Filter by IP..."
                            size="sm"
                        />
                    </div>

                    {{-- Browser Filter --}}
                    <div>
                        <flux:label
                            for="browserFilter"
                            class="text-xs"
                        >Browser</flux:label>
                        <flux:select
                            wire:model.live="browserFilter"
                            id="browserFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Browsers</flux:select.option>
                            <flux:select.option value="Chrome">Chrome</flux:select.option>
                            <flux:select.option value="Firefox">Firefox</flux:select.option>
                            <flux:select.option value="Safari">Safari</flux:select.option>
                            <flux:select.option value="Edge">Edge</flux:select.option>
                            <flux:select.option value="Opera">Opera</flux:select.option>
                            <flux:select.option value="Other">Other</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Platform Filter --}}
                    <div>
                        <flux:label
                            for="platformFilter"
                            class="text-xs"
                        >Platform</flux:label>
                        <flux:select
                            wire:model.live="platformFilter"
                            id="platformFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Platforms</flux:select.option>
                            <flux:select.option value="Windows">Windows</flux:select.option>
                            <flux:select.option value="macOS">macOS</flux:select.option>
                            <flux:select.option value="Linux">Linux</flux:select.option>
                            <flux:select.option value="iOS">iOS</flux:select.option>
                            <flux:select.option value="Android">Android</flux:select.option>
                            <flux:select.option value="Other">Other</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Device Filter --}}
                    <div>
                        <flux:label
                            for="deviceFilter"
                            class="text-xs"
                        >Device</flux:label>
                        <flux:select
                            wire:model.live="deviceFilter"
                            id="deviceFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Devices</flux:select.option>
                            <flux:select.option value="Desktop">Desktop</flux:select.option>
                            <flux:select.option value="Mobile">Mobile</flux:select.option>
                            <flux:select.option value="Tablet">Tablet</flux:select.option>
                            <flux:select.option value="Robot">Robot</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Referer Filter --}}
                    <div>
                        <flux:label
                            for="refererFilter"
                            class="text-xs"
                        >Referer</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="refererFilter"
                            id="refererFilter"
                            placeholder="Filter by referer..."
                            size="sm"
                        />
                    </div>

                    {{-- User Type Filter --}}
                    <div>
                        <flux:label
                            for="filter"
                            class="text-xs"
                        >User Type</flux:label>
                        <flux:select
                            wire:model.live="filter"
                            id="filter"
                            size="sm"
                        >
                            <flux:select.option value="all">All Users</flux:select.option>
                            <flux:select.option value="authenticated">Authenticated</flux:select.option>
                            <flux:select.option value="anonymous">Anonymous</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- User Search --}}
                    <div>
                        <flux:label
                            for="userSearch"
                            class="text-xs"
                        >User Search</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="userSearch"
                            id="userSearch"
                            placeholder="Search users..."
                            size="sm"
                        />
                    </div>

                    {{-- Country Filter --}}
                    <div>
                        <flux:label
                            for="countryFilter"
                            class="text-xs"
                        >Country</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="countryFilter"
                            id="countryFilter"
                            placeholder="Filter by country..."
                            size="sm"
                        />
                    </div>

                    {{-- Region Filter --}}
                    <div>
                        <flux:label
                            for="regionFilter"
                            class="text-xs"
                        >Region/State</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="regionFilter"
                            id="regionFilter"
                            placeholder="Filter by region..."
                            size="sm"
                        />
                    </div>

                    {{-- City Filter --}}
                    <div>
                        <flux:label
                            for="cityFilter"
                            class="text-xs"
                        >City</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="cityFilter"
                            id="cityFilter"
                            placeholder="Filter by city..."
                            size="sm"
                        />
                    </div>
                </div>
            </div>

            {{-- Visits Table --}}
            <div
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Visits</h3>
                </div>

                {{-- Top Pagination --}}
                @if ($this->events->hasPages())
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        {{ $this->events->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 800px;"
                    >
                        <thead class="bg-gray-100 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('created_at')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Time</span>
                                        @if ($sortBy === 'created_at')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('event_name')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Event</span>
                                        @if ($sortBy === 'event_name')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('visitor_id')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>User</span>
                                        @if ($sortBy === 'visitor_id')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('ip')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>IP</span>
                                        @if ($sortBy === 'ip')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('browser')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Browser</span>
                                        @if ($sortBy === 'browser')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('platform')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Platform</span>
                                        @if ($sortBy === 'platform')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('device')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Device</span>
                                        @if ($sortBy === 'device')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('country_name')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Location</span>
                                        @if ($sortBy === 'country_name')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    &nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->events as $event)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div class="text-xs">
                                            <div>{{ \Carbon\Carbon::parse($event->created_at)->format('M j, Y') }}
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-400">
                                                {{ \Carbon\Carbon::parse($event->created_at)->format('g:i A') }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        <div class="max-w-xs">
                                            <flux:badge
                                                size="sm"
                                                color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}"
                                            >
                                                <flux:icon
                                                    name="{{ \App\Enums\TrackingEventType::from($event->event_name)->getIcon() }}"
                                                    class="w-3 h-3 mr-1"
                                                />
                                                {{ $event->event_display_name }}
                                            </flux:badge>

                                            @if ($displayText = $this->getEventDisplayText($event))
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    @if ($eventUrl = $this->getEventUrl($event))
                                                        <a
                                                            href="{{ $eventUrl }}"
                                                            class="hover:text-gray-700 dark:hover:text-gray-300 underline hover:no-underline"
                                                            target="_blank"
                                                        >
                                                            {{ Str::limit($displayText, 40) }}
                                                        </a>
                                                    @else
                                                        {{ Str::limit($displayText, 40) }}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        @if ($this->getEventDisplayUser($event) && $this->getEventUserId($event))
                                            <div class="flex items-center space-x-2">
                                                <flux:avatar
                                                    circle="circle"
                                                    src="{{ $this->getEventDisplayUser($event)->profile_photo_url }}"
                                                    color="auto"
                                                    color:seed="{{ $this->getEventDisplayUser($event)->id }}"
                                                    size="xs"
                                                />
                                                <div class="flex flex-col">
                                                    <a
                                                        href="{{ route('user.show', ['userId' => $this->getEventUserId($event), 'slug' => Str::slug($this->getEventDisplayUser($event)->name)]) }}"
                                                        class="text-xs font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-24"
                                                    >
                                                        {{ $this->getEventDisplayUser($event)->name }}
                                                    </a>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">ID:
                                                        {{ $this->getEventUserId($event) }}</span>
                                                </div>
                                            </div>
                                        @elseif($this->getEventUserId($event))
                                            <div class="flex items-center space-x-2">
                                                <flux:avatar
                                                    circle="circle"
                                                    color="auto"
                                                    color:seed="{{ $this->getEventUserId($event) }}"
                                                    size="xs"
                                                />
                                                <div class="flex flex-col">
                                                    <span class="text-xs text-gray-900 dark:text-gray-100">
                                                        {{ $this->getEventDisplayName($event) ?? 'Unknown User' }}
                                                    </span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">ID:
                                                        {{ $this->getEventUserId($event) }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">Anonymous</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs font-mono">
                                        @if ($event->ip)
                                            <a
                                                href="https://whatismyipaddress.com/ip/{{ $event->ip }}"
                                                target="_blank"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300"
                                            >
                                                {{ $event->ip }}
                                            </a>
                                        @else
                                            <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs">
                                        @if ($event->browser)
                                            <button
                                                wire:click="$set('browserFilter', '{{ $event->browser }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300"
                                            >
                                                {{ $event->browser }}
                                            </button>
                                        @else
                                            <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs">
                                        @if ($event->platform)
                                            <button
                                                wire:click="$set('platformFilter', '{{ $event->platform }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300"
                                            >
                                                {{ $event->platform }}
                                            </button>
                                        @else
                                            <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs">
                                        @if ($event->device)
                                            <button
                                                wire:click="$set('deviceFilter', '{{ $event->device }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300"
                                            >
                                                {{ $event->device }}
                                            </button>
                                        @else
                                            <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs">
                                        @if ($event->country_name)
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="text-sm">{{ \App\Services\GeolocationService::getCountryFlag($event->country_code) }}</span>
                                                <div class="text-gray-900 dark:text-gray-100">
                                                    <button
                                                        wire:click="setGeographicFilter('country', '{{ $event->country_name }}')"
                                                        class="font-medium underline hover:text-gray-600 dark:hover:text-gray-300 text-left"
                                                    >
                                                        {{ $event->country_name }}
                                                    </button>
                                                    @if ($event->region_name || $event->city_name)
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            @if ($event->city_name && $event->region_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('city', '{{ $event->city_name }}')"
                                                                    class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                >
                                                                    {{ $event->city_name }}
                                                                </button>,
                                                                <button
                                                                    wire:click="setGeographicFilter('region', '{{ $event->region_name }}')"
                                                                    class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                >
                                                                    {{ $event->region_name }}
                                                                </button>
                                                            @elseif($event->city_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('city', '{{ $event->city_name }}')"
                                                                    class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                >
                                                                    {{ $event->city_name }}
                                                                </button>
                                                            @elseif($event->region_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('region', '{{ $event->region_name }}')"
                                                                    class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                >
                                                                    {{ $event->region_name }}
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs">
                                        <flux:button
                                            wire:click="showEventDetails({{ $event->id }})"
                                            variant="outline"
                                            size="xs"
                                            icon="eye"
                                        >
                                            Details
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="10"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        <flux:icon.chart-bar-square
                                            class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600"
                                        />
                                        <p class="text-gray-500 dark:text-gray-400">No events found for the selected
                                            filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->events->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->events->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Event Details Modal --}}
        <flux:modal
            wire:model.self="showEventModal"
            class="md:w-[800px] lg:w-[1000px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye"
                            class="w-8 h-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                Event Details
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                Complete event data in JSON format
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    @if ($selectedEvent)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 overflow-auto max-h-96">
                            <pre class="text-xs text-gray-900 dark:text-gray-100 whitespace-pre-wrap font-mono">{{ json_encode($selectedEvent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showEventModal = false"
                        variant="outline"
                        size="sm"
                    >
                        Close
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
