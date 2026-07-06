<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                    {{ __('API Analytics') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6 my-6">

            <flux:callout icon="information-circle" color="blue" inline>
                <flux:callout.text>
                    {{ __('These figures count only API requests that reached the origin server. A large share of API traffic is now served from the Cloudflare edge cache and never appears here. See the Cloudflare-handled total in the site footer for the full picture.') }}
                </flux:callout.text>
            </flux:callout>

            {{-- Range selector --}}
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-gray-400">
                    {{ __('Usage of the open v0 API, aggregated from per-request counters.') }}
                </p>
                <div class="w-48">
                    <flux:select wire:model.live="range" size="sm" variant="listbox">
                        @foreach ($this::RANGES as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            @if (! $this->hasData)
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 p-12 text-center">
                    <flux:icon.presentation-chart-line class="mx-auto h-10 w-10 text-gray-400" />
                    <h3 class="mt-3 text-lg font-semibold text-gray-100">{{ __('No usage recorded') }}</h3>
                    <p class="mt-1 text-sm text-gray-400">
                        {{ __('There is no API usage data for this range yet.') }}
                    </p>
                </div>
            @else
                {{-- Summary cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <flux:card class="space-y-1">
                        <flux:text size="sm">{{ __('Total requests') }}</flux:text>
                        <flux:heading size="xl">{{ number_format($this->summary['requests']) }}</flux:heading>
                    </flux:card>
                    <flux:card class="space-y-1">
                        <flux:text size="sm">{{ __('Error rate') }}</flux:text>
                        <flux:heading size="xl">{{ number_format($this->summary['error_rate'], 2) }}%</flux:heading>
                        <flux:text size="sm" class="text-gray-400">{{ number_format($this->summary['errors']) }} {{ __('errors') }}</flux:text>
                    </flux:card>
                    <flux:card class="space-y-1">
                        <flux:text size="sm">{{ __('Avg latency') }}</flux:text>
                        <flux:heading size="xl">{{ $this->summary['avg_latency_ms'] !== null ? number_format($this->summary['avg_latency_ms']).' ms' : '-' }}</flux:heading>
                    </flux:card>
                    <flux:card class="space-y-1">
                        <flux:text size="sm">{{ __('p95 latency') }}</flux:text>
                        <flux:heading size="xl">{{ $this->summary['p95_latency_ms'] !== null ? number_format($this->summary['p95_latency_ms']).' ms' : '-' }}</flux:heading>
                    </flux:card>
                </div>

                {{-- Volume trend --}}
                <div
                    class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 p-6"
                    x-data="{ hovered: null }"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-100">{{ __('Request volume') }}</h3>
                        <div class="text-sm text-gray-400">
                            <span x-show="! hovered">{{ __('Peak') }}: {{ number_format($this->peakRequests) }} {{ __('req') }}</span>
                            <span x-show="hovered" x-text="hovered"></span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        {{-- Vertical scale --}}
                        <div class="flex flex-col justify-between h-40 w-14 shrink-0 text-right text-xs text-gray-500">
                            <span>{{ number_format($this->peakRequests) }}</span>
                            <span>{{ number_format((int) round($this->peakRequests / 2)) }}</span>
                            <span>0</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-end gap-px h-40 border-l border-b border-gray-700">
                                @foreach ($this->timeSeries as $point)
                                    <div
                                        class="flex-1 h-full flex items-end cursor-default"
                                        x-on:mouseenter="hovered = @js($point['label'].': '.number_format($point['requests']).' '.__('requests'))"
                                        x-on:mouseleave="hovered = null"
                                        x-on:click="hovered = @js($point['label'].': '.number_format($point['requests']).' '.__('requests'))"
                                    >
                                        <div
                                            class="w-full bg-cyan-500/70 hover:bg-cyan-500 rounded-t"
                                            style="height: {{ max($point['percent'], 1) }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-gray-400">
                                <span>{{ $this->timeSeries[0]['label'] ?? '' }}</span>
                                <span>{{ $this->timeSeries[array_key_last($this->timeSeries)]['label'] ?? '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Endpoints --}}
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 overflow-hidden">
                    <h3 class="text-lg font-semibold text-gray-100 p-6 pb-3">{{ __('Endpoints') }}</h3>
                    <div class="px-6 pb-4 overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Endpoint') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Requests') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Error rate') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('4xx / 5xx') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('429') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Avg') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('p95') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->endpoints as $endpoint)
                                <flux:table.row :key="$endpoint['route_name']">
                                    <flux:table.cell class="font-mono text-xs">{{ $endpoint['route_name'] }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($endpoint['requests']) }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:badge size="sm" :color="$endpoint['error_rate'] > 5 ? 'red' : ($endpoint['error_rate'] > 0 ? 'amber' : 'green')">
                                            {{ number_format($endpoint['error_rate'], 1) }}%
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($endpoint['errors_4xx']) }} / {{ number_format($endpoint['errors_5xx']) }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($endpoint['throttled']) }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $endpoint['avg_latency_ms'] !== null ? $endpoint['avg_latency_ms'].' ms' : '-' }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $endpoint['p95_latency_ms'] !== null ? $endpoint['p95_latency_ms'].' ms' : '-' }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    </div>
                </div>

                {{-- Unmatched requests --}}
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 overflow-hidden">
                    <div class="flex items-baseline justify-between p-6 pb-3">
                        <h3 class="text-lg font-semibold text-gray-100">{{ __('Unmatched requests') }}</h3>
                        <span class="text-sm text-gray-400">{{ number_format($this->unmatchedTotal) }} {{ __('requests') }}</span>
                    </div>
                    <p class="px-6 pb-3 text-sm text-gray-400">
                        {{ __('Requests under the v0 API surface that matched no registered endpoint. These are excluded from the stats above.') }}
                    </p>
                    @if ($this->unmatchedRequests === [])
                        <p class="px-6 pb-6 text-sm text-gray-400">{{ __('No unmatched paths recorded for this range.') }}</p>
                    @else
                        <div class="px-6 pb-4 overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Path') }}</flux:table.column>
                                <flux:table.column>{{ __('Method') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Status') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Requests') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Last seen') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->unmatchedRequests as $unmatched)
                                    <flux:table.row :key="$unmatched['method'].' '.$unmatched['status_code'].' '.$unmatched['path']">
                                        <flux:table.cell class="font-mono text-xs">{{ $unmatched['path'] }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" color="zinc">{{ $unmatched['method'] }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell align="end">
                                            <flux:badge size="sm" :color="$unmatched['status_code'] >= 500 ? 'red' : 'amber'">
                                                {{ $unmatched['status_code'] }}
                                            </flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell align="end">{{ number_format($unmatched['requests']) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="whitespace-nowrap">{{ $unmatched['last_seen']->diffForHumans() }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                        </div>
                    @endif
                </div>

                {{-- Top clients --}}
                <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 overflow-hidden">
                    <h3 class="text-lg font-semibold text-gray-100 p-6 pb-3">{{ __('Top clients') }}</h3>
                    @if ($this->topClients === [])
                        <p class="px-6 pb-6 text-sm text-gray-400">{{ __('No client data for this range.') }}</p>
                    @else
                        <div class="px-6 pb-4 overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('IP address') }}</flux:table.column>
                                <flux:table.column>{{ __('Location') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Requests') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Share') }}</flux:table.column>
                                <flux:table.column align="end">{{ $this->range === '24h' ? __('Active minutes') : __('Active days') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Last seen') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->topClients as $client)
                                    <flux:table.row :key="$client['ip']">
                                        <flux:table.cell class="font-mono text-xs">{{ $client['ip'] }}</flux:table.cell>
                                        <flux:table.cell>
                                            @if ($client['country_name'] !== null)
                                                <span class="mr-1">{{ $client['flag'] }}</span>{{ $client['city_name'] !== null ? $client['city_name'].', ' : '' }}{{ $client['country_name'] }}
                                            @else
                                                <span class="text-gray-500">{{ __('Unknown') }}</span>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell align="end">{{ number_format($client['requests']) }}</flux:table.cell>
                                        <flux:table.cell align="end">{{ number_format($client['share'], 1) }}%</flux:table.cell>
                                        <flux:table.cell align="end">{{ number_format($client['active_periods']) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="whitespace-nowrap">{{ $client['last_seen']->diffForHumans() }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</div>
