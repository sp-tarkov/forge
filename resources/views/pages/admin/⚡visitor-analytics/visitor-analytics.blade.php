<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('Visitor Analytics') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <span class="flex-shrink-0 text-sm font-medium text-gray-300">Filtering:</span>
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
                class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {{-- Date Range Filter --}}
                    <div>
                        <flux:label class="text-xs">Date From</flux:label>
                        <flux:date-picker
                            wire:model.live="dateFrom"
                            size="sm"
                            clearable
                        />
                    </div>

                    <div>
                        <flux:label class="text-xs">Date To</flux:label>
                        <flux:date-picker
                            wire:model.live="dateTo"
                            size="sm"
                            clearable
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
                            variant="listbox"
                            searchable
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
                            wire:model.live.debounce.750ms="ipFilter"
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
                            variant="listbox"
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
                            variant="listbox"
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
                            variant="listbox"
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
                            wire:model.live.debounce.750ms="refererFilter"
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
                            variant="listbox"
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
                            wire:model.live.debounce.750ms="userSearch"
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
                            wire:model.live.debounce.750ms="countryFilter"
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
                            wire:model.live.debounce.750ms="regionFilter"
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
                            wire:model.live.debounce.750ms="cityFilter"
                            id="cityFilter"
                            placeholder="Filter by city..."
                            size="sm"
                        />
                    </div>
                </div>
            </div>

            {{-- Visits Table --}}
            <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="border-b border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-100">Recent Visits</h3>
                </div>

                @if ($this->listTimedOut)
                    <div class="border-b border-gray-700 px-6 py-4">
                        <flux:callout
                            icon="clock"
                            color="amber"
                        >
                            <flux:callout.heading>This filter combination took too long to load.</flux:callout.heading>
                            <flux:callout.text>Try a shorter date range or more specific filters, then try again.
                            </flux:callout.text>
                        </flux:callout>
                    </div>
                @endif

                {{-- Top Pagination --}}
                @if ($this->events->hasPages())
                    <div class="border-b border-gray-700 bg-gray-800 px-6 py-4">
                        {{ $this->events->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 800px;"
                    >
                        <thead class="bg-gray-900">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('created_at')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Time</span>
                                        @if ($sortBy === 'created_at')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('event_name')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Event</span>
                                        @if ($sortBy === 'event_name')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('visitor_id')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>User</span>
                                        @if ($sortBy === 'visitor_id')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('ip')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>IP</span>
                                        @if ($sortBy === 'ip')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('browser')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Browser</span>
                                        @if ($sortBy === 'browser')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('platform')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Platform</span>
                                        @if ($sortBy === 'platform')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('device')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Device</span>
                                        @if ($sortBy === 'device')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('country_name')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Location</span>
                                        @if ($sortBy === 'country_name')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    &nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-800">
                            @forelse($this->events as $event)
                                <tr class="hover:bg-gray-700">
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-100">
                                        <div class="text-xs">
                                            <div>{{ \Carbon\Carbon::parse($event->created_at)->format('M j, Y') }}
                                            </div>
                                            <div class="text-gray-400">
                                                {{ \Carbon\Carbon::parse($event->created_at)->format('g:i A') }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-100">
                                        <div class="max-w-xs">
                                            <flux:badge
                                                size="sm"
                                                color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}"
                                            >
                                                <flux:icon
                                                    name="{{ \App\Enums\TrackingEventType::from($event->event_name)->getIcon() }}"
                                                    class="mr-1 h-3 w-3"
                                                />
                                                {{ $event->event_display_name }}
                                            </flux:badge>

                                            @if ($displayText = $this->getEventDisplayText($event))
                                                <div class="mt-1 text-xs text-gray-400">
                                                    @if ($eventUrl = $this->getEventUrl($event))
                                                        <a
                                                            href="{{ $eventUrl }}"
                                                            class="underline hover:text-gray-300 hover:no-underline"
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
                                    <td class="whitespace-nowrap px-4 py-4 text-sm">
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
                                                        class="max-w-24 truncate text-xs font-medium text-gray-100 underline hover:text-gray-300"
                                                    >
                                                        {{ $this->getEventDisplayUser($event)->name }}
                                                    </a>
                                                    <span class="text-xs text-gray-400">ID:
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
                                                    <span class="text-xs text-gray-100">
                                                        {{ $this->getEventDisplayName($event) ?? 'Unknown User' }}
                                                    </span>
                                                    <span class="text-xs text-gray-400">ID:
                                                        {{ $this->getEventUserId($event) }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500">Anonymous</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 font-mono text-xs">
                                        @if ($event->ip)
                                            <a
                                                href="https://whatismyipaddress.com/ip/{{ $event->ip }}"
                                                target="_blank"
                                                class="text-gray-100 underline hover:text-gray-300"
                                            >
                                                {{ $event->ip }}
                                            </a>
                                        @else
                                            <span class="text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs">
                                        @if ($event->browser)
                                            <button
                                                wire:click="$set('browserFilter', '{{ $event->browser }}')"
                                                class="text-gray-100 underline hover:text-gray-300"
                                            >
                                                {{ $event->browser }}
                                            </button>
                                        @else
                                            <span class="text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs">
                                        @if ($event->platform)
                                            <button
                                                wire:click="$set('platformFilter', '{{ $event->platform }}')"
                                                class="text-gray-100 underline hover:text-gray-300"
                                            >
                                                {{ $event->platform }}
                                            </button>
                                        @else
                                            <span class="text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs">
                                        @if ($event->device)
                                            <button
                                                wire:click="$set('deviceFilter', '{{ $event->device }}')"
                                                class="text-gray-100 underline hover:text-gray-300"
                                            >
                                                {{ $event->device }}
                                            </button>
                                        @else
                                            <span class="text-gray-100">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs">
                                        @if ($event->country_name)
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="text-sm">{{ \App\Services\GeolocationService::getCountryFlag($event->country_code) }}</span>
                                                <div class="text-gray-100">
                                                    <button
                                                        wire:click="setGeographicFilter('country', '{{ $event->country_name }}')"
                                                        class="text-left font-medium underline hover:text-gray-300"
                                                    >
                                                        {{ $event->country_name }}
                                                    </button>
                                                    @if ($event->region_name || $event->city_name)
                                                        <div class="text-xs text-gray-400">
                                                            @if ($event->city_name && $event->region_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('city', '{{ $event->city_name }}')"
                                                                    class="underline hover:text-gray-300"
                                                                >
                                                                    {{ $event->city_name }}
                                                                </button>,
                                                                <button
                                                                    wire:click="setGeographicFilter('region', '{{ $event->region_name }}')"
                                                                    class="underline hover:text-gray-300"
                                                                >
                                                                    {{ $event->region_name }}
                                                                </button>
                                                            @elseif($event->city_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('city', '{{ $event->city_name }}')"
                                                                    class="underline hover:text-gray-300"
                                                                >
                                                                    {{ $event->city_name }}
                                                                </button>
                                                            @elseif($event->region_name)
                                                                <button
                                                                    wire:click="setGeographicFilter('region', '{{ $event->region_name }}')"
                                                                    class="underline hover:text-gray-300"
                                                                >
                                                                    {{ $event->region_name }}
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-500">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs">
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
                                        class="px-6 py-12 text-center text-gray-400"
                                    >
                                        <flux:icon.chart-bar-square class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                                        <p class="text-gray-400">No events found for the selected
                                            filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->events->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
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
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye"
                            class="h-8 w-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                Event Details
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                Complete event data in JSON format
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    @if ($selectedEvent)
                        <div class="max-h-96 overflow-auto rounded-lg bg-gray-800 p-4">
                            <pre class="whitespace-pre-wrap font-mono text-xs text-gray-100">{{ json_encode($selectedEvent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
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
