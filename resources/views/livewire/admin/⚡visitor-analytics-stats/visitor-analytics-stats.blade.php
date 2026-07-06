<div>
    @if ($this->stats !== null)
        <div class="space-y-6">
            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Total Events</p>
                            <p class="mt-1 text-3xl font-bold text-gray-100">
                                {{ number_format($this->stats->totalEvents) }}</p>
                        </div>
                        <div class="rounded-lg bg-blue-900/20 p-3">
                            <flux:icon.chart-bar-square class="h-6 w-6 text-blue-400" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Unique Users</p>
                            <p class="mt-1 text-3xl font-bold text-gray-100">
                                {{ number_format($this->stats->uniqueUsers) }}</p>
                        </div>
                        <div class="rounded-lg bg-green-900/20 p-3">
                            <flux:icon.users class="h-6 w-6 text-green-400" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Authenticated</p>
                            <p class="mt-1 text-3xl font-bold text-gray-100">
                                {{ number_format($this->stats->authenticatedEvents) }}</p>
                        </div>
                        <div class="rounded-lg bg-purple-900/20 p-3">
                            <flux:icon.user class="h-6 w-6 text-purple-400" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Anonymous</p>
                            <p class="mt-1 text-3xl font-bold text-gray-100">
                                {{ number_format($this->stats->anonymousEvents) }}</p>
                        </div>
                        <div class="rounded-lg bg-orange-900/20 p-3">
                            <flux:icon.user class="h-6 w-6 text-orange-400" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Countries</p>
                            <p class="mt-1 text-3xl font-bold text-gray-100">
                                {{ number_format($this->stats->uniqueCountries) }}</p>
                        </div>
                        <div class="rounded-lg bg-indigo-900/20 p-3">
                            <flux:icon.globe-alt class="h-6 w-6 text-indigo-400" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Daily Events Chart --}}
            @if (!empty($this->stats->dailyEvents))
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-100">Daily Events</h3>
                    <flux:chart
                        :value="$this->stats->dailyEvents"
                        class="aspect-3/1"
                    >
                        <flux:chart.svg>
                            <flux:chart.line
                                field="events"
                                class="text-blue-500"
                            />
                            <flux:chart.area
                                field="events"
                                class="text-blue-900/30"
                            />
                            <flux:chart.point
                                field="events"
                                class="text-blue-400"
                            />
                            <flux:chart.axis
                                axis="x"
                                field="date"
                                :format="['month' => 'short', 'day' => 'numeric']"
                            >
                                <flux:chart.axis.line />
                                <flux:chart.axis.tick />
                            </flux:chart.axis>
                            <flux:chart.axis axis="y">
                                <flux:chart.axis.grid />
                                <flux:chart.axis.tick />
                            </flux:chart.axis>
                        </flux:chart.svg>
                        <flux:chart.cursor />
                        <flux:chart.tooltip>
                            <flux:chart.tooltip.heading
                                field="date"
                                :format="['month' => 'long', 'day' => 'numeric']"
                            />
                            <flux:chart.tooltip.value
                                field="events"
                                label="Events"
                            />
                        </flux:chart.tooltip>
                    </flux:chart>
                </div>
            @endif

            {{-- Top Stats --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                {{-- Top Events --}}
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-100">Top Events</h3>
                    <div class="space-y-3">
                        @forelse($this->stats->topEvents as $event)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2 truncate pr-2">
                                    <flux:icon
                                        name="{{ \App\Enums\TrackingEventType::from($event['event_name'])->getIcon() }}"
                                        class="h-4 w-4"
                                        color="{{ \App\Enums\TrackingEventType::from($event['event_name'])->getColor() }}"
                                    />
                                    <span
                                        class="truncate text-sm text-gray-300">{{ \App\Enums\TrackingEventType::from($event['event_name'])->getName() }}</span>
                                </div>
                                <flux:badge
                                    size="sm"
                                    color="{{ \App\Enums\TrackingEventType::from($event['event_name'])->getColor() }}"
                                >{{ number_format($event['count']) }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-gray-400">No data available</p>
                        @endforelse
                    </div>
                </div>

                {{-- Top Browsers --}}
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-100">Top Browsers</h3>
                    <div class="space-y-3">
                        @forelse($this->stats->topBrowsers as $browser)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-300">{{ $browser['browser'] ?: 'Unknown' }}</span>
                                <flux:badge
                                    size="sm"
                                    color="green"
                                >{{ number_format($browser['count']) }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-gray-400">No data available</p>
                        @endforelse
                    </div>
                </div>

                {{-- Top Platforms --}}
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-100">Top Platforms</h3>
                    <div class="space-y-3">
                        @forelse($this->stats->topPlatforms as $platform)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-300">{{ $platform['platform'] ?: 'Unknown' }}</span>
                                <flux:badge
                                    size="sm"
                                    color="purple"
                                >{{ number_format($platform['count']) }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-gray-400">No data available</p>
                        @endforelse
                    </div>
                </div>

                {{-- Top Countries --}}
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-100">Top Countries</h3>
                    <div class="space-y-3">
                        @forelse($this->stats->topCountries as $country)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span
                                        class="text-lg">{{ $country['country_code'] ? \App\Services\GeolocationService::getCountryFlag($country['country_code']) : '' }}</span>
                                    <span class="text-sm text-gray-300">
                                        {{ $country['country_name'] ?: 'Unknown' }}
                                    </span>
                                </div>
                                <flux:badge
                                    size="sm"
                                    color="indigo"
                                >{{ number_format($country['count']) }}</flux:badge>
                            </div>
                        @empty
                            <p class="text-gray-400">No data available</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @elseif ($this->hasFailed())
        <div class="space-y-4 rounded-lg border border-gray-700 bg-gray-900 p-12 text-center shadow-sm">
            <flux:icon.exclamation-triangle class="mx-auto h-12 w-12 text-amber-500" />
            <div>
                <p class="font-medium text-gray-100">The analytics computation failed.</p>
                @if ($this->failureMessage())
                    <p class="mt-1 text-sm text-gray-400">{{ $this->failureMessage() }}</p>
                @endif
            </div>
            <flux:button
                variant="primary"
                size="sm"
                icon="arrow-path"
                wire:click="retryStats"
            >Retry</flux:button>
        </div>
    @else
        <div
            wire:poll.3s="checkStats"
            role="status"
            class="space-y-4"
        >
            <div class="flex items-center gap-2 text-sm text-gray-400">
                <flux:icon.loading class="h-4 w-4" />
                @if ($this->isProcessing())
                    Computing analytics...
                @else
                    Queued for analysis...
                @endif
            </div>

            <flux:skeleton.group
                animate="shimmer"
                class="space-y-6"
                aria-hidden="true"
            >
                {{-- Stats Cards Skeleton --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="space-y-2">
                                    <flux:skeleton class="h-3 w-20 rounded" />
                                    <flux:skeleton class="h-8 w-24 rounded" />
                                </div>
                                <div class="rounded-lg bg-gray-800 p-3">
                                    <flux:skeleton class="size-6 rounded" />
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                {{-- Top Stats Skeleton --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                            <flux:skeleton class="mb-4 h-5 w-24 rounded" />
                            <div class="space-y-3">
                                @for ($j = 0; $j < 5; $j++)
                                    <div class="flex items-center justify-between">
                                        <flux:skeleton class="h-4 w-32 rounded" />
                                        <flux:skeleton class="h-5 w-12 rounded-full" />
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endfor
                </div>
            </flux:skeleton.group>
        </div>
    @endif
</div>
