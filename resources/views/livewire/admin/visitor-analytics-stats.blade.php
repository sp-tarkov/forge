<div class="space-y-6">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                        Total Events</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ number_format($stats['total_events']) }}</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <flux:icon.chart-bar-square class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                        Unique Users</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ number_format($stats['unique_users']) }}</p>
                </div>
                <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <flux:icon.users class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                        Authenticated</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ number_format($stats['authenticated_events']) }}</p>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <flux:icon.user class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                        Anonymous</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ number_format($stats['anonymous_events']) }}</p>
                </div>
                <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                    <flux:icon.user class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                        Countries</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ number_format($stats['unique_countries']) }}</p>
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
                            <flux:icon
                                name="{{ \App\Enums\TrackingEventType::from($event->event_name)->getIcon() }}"
                                class="w-4 h-4"
                                color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}"
                            />
                            <span
                                class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ \App\Enums\TrackingEventType::from($event->event_name)->getName() }}</span>
                        </div>
                        <flux:badge
                            size="sm"
                            color="{{ \App\Enums\TrackingEventType::from($event->event_name)->getColor() }}"
                        >{{ number_format($event->count) }}</flux:badge>
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
                        <span
                            class="text-sm text-gray-700 dark:text-gray-300">{{ $browser->browser ?: 'Unknown' }}</span>
                        <flux:badge
                            size="sm"
                            color="green"
                        >{{ number_format($browser->count) }}</flux:badge>
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
                        <span
                            class="text-sm text-gray-700 dark:text-gray-300">{{ $platform->platform ?: 'Unknown' }}</span>
                        <flux:badge
                            size="sm"
                            color="purple"
                        >{{ number_format($platform->count) }}</flux:badge>
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
                            <span
                                class="text-lg">{{ $country->country_code ? \App\Services\GeolocationService::getCountryFlag($country->country_code) : '' }}</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $country->country_name ?: 'Unknown' }}
                            </span>
                        </div>
                        <flux:badge
                            size="sm"
                            color="indigo"
                        >{{ number_format($country->count) }}</flux:badge>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400">No data available</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
