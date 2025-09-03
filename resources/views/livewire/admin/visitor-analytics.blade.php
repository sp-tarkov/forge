@php
use Illuminate\Support\Str;
@endphp

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
        @if($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Filtering:</span>
                    <div class="min-w-0">
                        <flux:breadcrumbs class="inline-flex flex-wrap">
                            @foreach($this->getActiveFilters() as $index => $filter)
                                <flux:breadcrumbs.item separator="slash">{{ $filter }}</flux:breadcrumbs.item>
                            @endforeach
                        </flux:breadcrumbs>
                    </div>
                </div>
            </div>
        @endif
        <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Events</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['total_events']) }}</p>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon.chart-bar-square class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Unique Users</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['unique_users']) }}</p>
                    </div>
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <flux:icon.users class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Authenticated</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['authenticated_events']) }}</p>
                    </div>
                    <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <flux:icon.user class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Anonymous</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['anonymous_events']) }}</p>
                    </div>
                    <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                        <flux:icon.user class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Countries</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['unique_countries']) }}</p>
                    </div>
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                        <flux:icon.globe-alt class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Stats --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Top Events --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Events</h3>
                <div class="space-y-3">
                    @forelse($stats['top_events'] as $event)
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-2 truncate pr-2">
                                <flux:icon name="{{ \App\Enums\TrackingEventType::from($event->event_name)->getIcon() }}" class="w-4 h-4" color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}" />
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ \App\Enums\TrackingEventType::from($event->event_name)->getName() }}</span>
                            </div>
                            <flux:badge size="sm" color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}">{{ number_format($event->count) }}</flux:badge>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data available</p>
                    @endforelse
                </div>
            </div>

            {{-- Top Browsers --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Browsers</h3>
                <div class="space-y-3">
                    @forelse($stats['top_browsers'] as $browser)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $browser->browser ?: 'Unknown' }}</span>
                            <flux:badge size="sm" color="green">{{ number_format($browser->count) }}</flux:badge>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data available</p>
                    @endforelse
                </div>
            </div>

            {{-- Top Platforms --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Platforms</h3>
                <div class="space-y-3">
                    @forelse($stats['top_platforms'] as $platform)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $platform->platform ?: 'Unknown' }}</span>
                            <flux:badge size="sm" color="purple">{{ number_format($platform->count) }}</flux:badge>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data available</p>
                    @endforelse
                </div>
            </div>

            {{-- Top Countries --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Countries</h3>
                <div class="space-y-3">
                    @forelse($stats['top_countries'] as $country)
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-2">
                                <span class="text-lg">{{ $country->country_code ? \App\Services\GeolocationService::getCountryFlag($country->country_code) : '🏳️' }}</span>
                                <button
                                    wire:click="setGeographicFilter('country', '{{ $country->country_name }}')"
                                    class="text-sm text-gray-700 dark:text-gray-300 underline hover:text-gray-600 dark:hover:text-gray-400"
                                >
                                    {{ $country->country_name ?: 'Unknown' }}
                                </button>
                            </div>
                            <flux:badge size="sm" color="indigo">{{ number_format($country->count) }}</flux:badge>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Filters Section --}}
        <div id="filters-container" class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                <flux:button wire:click="resetFilters" variant="outline" size="sm" icon="x-mark">
                    Clear All
                </flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                {{-- Date Range Filter --}}
                <div>
                    <flux:label for="dateFrom" class="text-xs">Date From</flux:label>
                    <flux:input type="date" wire:model.live="dateFrom" id="dateFrom" size="sm" />
                </div>

                <div>
                    <flux:label for="dateTo" class="text-xs">Date To</flux:label>
                    <flux:input type="date" wire:model.live="dateTo" id="dateTo" size="sm" />
                </div>

                {{-- Event Filter --}}
                <div>
                    <flux:label for="eventFilter" class="text-xs">Event</flux:label>
                    <flux:input wire:model.live.debounce.300ms="eventFilter" id="eventFilter" placeholder="Filter by event..." size="sm" />
                </div>

                <div>
                    <flux:label for="eventMatchType" class="text-xs">Event Match</flux:label>
                    <flux:select wire:model.live="eventMatchType" id="eventMatchType" size="sm">
                        <flux:select.option value="contains">Contains</flux:select.option>
                        <flux:select.option value="exact">Exact Match</flux:select.option>
                    </flux:select>
                </div>

                {{-- IP Address Filter --}}
                <div>
                    <flux:label for="ipFilter" class="text-xs">IP Address</flux:label>
                    <flux:input wire:model.live.debounce.300ms="ipFilter" id="ipFilter" placeholder="Filter by IP..." size="sm" />
                </div>

                {{-- Browser Filter --}}
                <div>
                    <flux:label for="browserFilter" class="text-xs">Browser</flux:label>
                    <flux:select wire:model.live="browserFilter" id="browserFilter" size="sm">
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
                    <flux:label for="platformFilter" class="text-xs">Platform</flux:label>
                    <flux:select wire:model.live="platformFilter" id="platformFilter" size="sm">
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
                    <flux:label for="deviceFilter" class="text-xs">Device</flux:label>
                    <flux:select wire:model.live="deviceFilter" id="deviceFilter" size="sm">
                        <flux:select.option value="">All Devices</flux:select.option>
                        <flux:select.option value="Desktop">Desktop</flux:select.option>
                        <flux:select.option value="Mobile">Mobile</flux:select.option>
                        <flux:select.option value="Tablet">Tablet</flux:select.option>
                        <flux:select.option value="Robot">Robot</flux:select.option>
                    </flux:select>
                </div>

                {{-- Referer Filter --}}
                <div>
                    <flux:label for="refererFilter" class="text-xs">Referer</flux:label>
                    <flux:input wire:model.live.debounce.300ms="refererFilter" id="refererFilter" placeholder="Filter by referer..." size="sm" />
                </div>


                {{-- User Type Filter --}}
                <div>
                    <flux:label for="filter" class="text-xs">User Type</flux:label>
                    <flux:select wire:model.live="filter" id="filter" size="sm">
                        <flux:select.option value="all">All Users</flux:select.option>
                        <flux:select.option value="authenticated">Authenticated</flux:select.option>
                        <flux:select.option value="anonymous">Anonymous</flux:select.option>
                    </flux:select>
                </div>

                {{-- User Search --}}
                <div>
                    <flux:label for="userSearch" class="text-xs">User Search</flux:label>
                    <flux:input wire:model.live.debounce.300ms="userSearch" id="userSearch" placeholder="Search users..." size="sm" />
                </div>

                {{-- Country Filter --}}
                <div>
                    <flux:label for="countryFilter" class="text-xs">Country</flux:label>
                    <flux:input wire:model.live.debounce.300ms="countryFilter" id="countryFilter" placeholder="Filter by country..." size="sm" />
                </div>

                {{-- Region Filter --}}
                <div>
                    <flux:label for="regionFilter" class="text-xs">Region/State</flux:label>
                    <flux:input wire:model.live.debounce.300ms="regionFilter" id="regionFilter" placeholder="Filter by region..." size="sm" />
                </div>

                {{-- City Filter --}}
                <div>
                    <flux:label for="cityFilter" class="text-xs">City</flux:label>
                    <flux:input wire:model.live.debounce.300ms="cityFilter" id="cityFilter" placeholder="Filter by city..." size="sm" />
                </div>
            </div>
        </div>

        {{-- Visits Table --}}
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Visits</h3>
            </div>

            {{-- Top Pagination --}}
            @if($this->events->hasPages())
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    {{ $this->events->links(data: ['scrollTo' => '#filters-container']) }}
                </div>
            @endif

            <div class="w-full overflow-x-auto">
                <table class="w-full table-auto" style="min-width: 800px;">
                    <thead class="bg-gray-100 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('created_at')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Time</span>
                                    @if($sortBy === 'created_at')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('event_name')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Event</span>
                                    @if($sortBy === 'event_name')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('visitor_id')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>User</span>
                                    @if($sortBy === 'visitor_id')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('ip')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>IP</span>
                                    @if($sortBy === 'ip')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('browser')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Browser</span>
                                    @if($sortBy === 'browser')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('platform')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Platform</span>
                                    @if($sortBy === 'platform')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('device')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Device</span>
                                    @if($sortBy === 'device')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button
                                    type="button"
                                    wire:click="sortByColumn('country_name')"
                                    class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                >
                                    <span>Location</span>
                                    @if($sortBy === 'country_name')
                                        <flux:icon.chevron-down class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->events as $event)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    <div class="text-xs">
                                        <div>{{ \Carbon\Carbon::parse($event->created_at)->format('M j, Y') }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($event->created_at)->format('g:i A') }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    <div class="max-w-xs">
                                        <flux:badge size="sm" color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}">
                                            <flux:icon name="{{ \App\Enums\TrackingEventType::from($event->event_name)->getIcon() }}" class="w-3 h-3 mr-1" />
                                            {{ $event->event_display_name }}
                                        </flux:badge>
                                        
                                        @if($displayText = $this->getEventDisplayText($event))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                @if($eventUrl = $this->getEventUrl($event))
                                                    <a href="{{ $eventUrl }}" class="hover:text-gray-700 dark:hover:text-gray-300 underline hover:no-underline" target="_blank">
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
                                    @if($event->visitor_id && $event->user)
                                        <div class="flex items-center space-x-2">
                                            <flux:avatar
                                                circle="circle"
                                                src="{{ $event->user->profile_photo_url }}"
                                                color="auto"
                                                color:seed="{{ $event->user->id }}"
                                                size="xs"
                                            />
                                            <div class="flex flex-col">
                                                <a href="{{ route('user.show', ['userId' => $event->visitor_id, 'slug' => Str::slug($event->user->name)]) }}"
                                                   class="text-xs font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-24">
                                                    {{ $event->user->name }}
                                                </a>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $event->visitor_id }}</span>
                                            </div>
                                        </div>
                                    @elseif($event->visitor_id)
                                        <div class="flex items-center space-x-2">
                                            <flux:avatar
                                                circle="circle"
                                                color="auto"
                                                color:seed="{{ $event->visitor_id }}"
                                                size="xs"
                                            />
                                            <div class="flex flex-col">
                                                <span class="text-xs text-gray-900 dark:text-gray-100">Unknown User</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $event->visitor_id }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">Anonymous</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-mono">
                                    @if($event->ip)
                                        <a href="https://whatismyipaddress.com/ip/{{ $event->ip }}"
                                           target="_blank"
                                           class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300">
                                            {{ $event->ip }}
                                        </a>
                                    @else
                                        <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    @if($event->browser)
                                        <button wire:click="$set('browserFilter', '{{ $event->browser }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300">
                                            {{ $event->browser }}
                                        </button>
                                    @else
                                        <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    @if($event->platform)
                                        <button wire:click="$set('platformFilter', '{{ $event->platform }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300">
                                            {{ $event->platform }}
                                        </button>
                                    @else
                                        <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    @if($event->device)
                                        <button wire:click="$set('deviceFilter', '{{ $event->device }}')"
                                                class="text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300">
                                            {{ $event->device }}
                                        </button>
                                    @else
                                        <span class="text-gray-900 dark:text-gray-100">Unknown</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    @if($event->country_name)
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm">{{ \App\Services\GeolocationService::getCountryFlag($event->country_code) }}</span>
                                            <div class="text-gray-900 dark:text-gray-100">
                                                <button
                                                    wire:click="setGeographicFilter('country', '{{ $event->country_name }}')"
                                                    class="font-medium underline hover:text-gray-600 dark:hover:text-gray-300 text-left"
                                                >
                                                    {{ $event->country_name }}
                                                </button>
                                                @if($event->region_name || $event->city_name)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        @if($event->city_name && $event->region_name)
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
                                <td colspan="10" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <flux:icon.chart-bar-square class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                                    <p class="text-gray-500 dark:text-gray-400">No events found for the selected filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->events->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $this->events->links(data: ['scrollTo' => '#filters-container']) }}
                </div>
            @endif
        </div>
    </div>

    {{-- Event Details Modal --}}
    <flux:modal wire:model.self="showEventModal" class="md:w-[800px] lg:w-[1000px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="eye" class="w-8 h-8 text-blue-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
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
                @if($selectedEvent)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 overflow-auto max-h-96">
                        <pre class="text-xs text-gray-900 dark:text-gray-100 whitespace-pre-wrap font-mono">{{ json_encode($selectedEvent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showEventModal = false" variant="outline" size="sm">
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
