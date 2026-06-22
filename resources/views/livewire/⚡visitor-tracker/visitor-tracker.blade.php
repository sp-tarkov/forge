<div
    wire:ignore.self
    x-data="visitorTracker(@js($peakCount), @js($peakDate), $wire)"
    class="text-xs text-gray-400"
>
    {{-- Connection error state --}}
    <template x-if="connectionError">
        <div class="flex items-center justify-end space-x-2">
            <div class="relative flex h-2 w-2">
                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
            </div>
            <div>
                <span class="text-red-400">Connection error</span>
            </div>
        </div>
    </template>

    {{-- Normal state with visitor count --}}
    <template x-if="!connectionError">
        <div>
            <div class="flex items-center justify-end space-x-2">
                <div class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </div>
                <div>
                    <span
                        class="font-medium text-gray-300"
                        x-text="totalCount"
                    ></span>
                    <span x-text="totalCount === 1 ? 'user currently online' : 'users currently online'"></span>
                    <template x-if="authCount > 0">
                        <span
                            class="text-gray-500"
                            x-text="`(${authCount} ${authCount === 1 ? 'member' : 'members'})`"
                        ></span>
                    </template>
                </div>
            </div>

            {{-- Peak display --}}
            <template x-if="peakCount > 0 && peakDate">
                <div class="text-right mt-1">
                    <span class="text-gray-500">Peak:</span>
                    <span
                        class="font-medium text-gray-400"
                        x-text="peakCount"
                    ></span>
                    <span
                        class="text-gray-500"
                        x-text="`on ${peakDate}`"
                    ></span>
                </div>
            </template>

            {{-- API requests served in the last 24 hours --}}
            @if ($apiRequests24h > 0)
                <div class="text-right mt-1">
                    <span class="font-medium text-gray-400">{{ number_format($apiRequests24h) }}</span>
                    <span class="text-gray-500">{{ __('API requests in the last 24h') }}</span>
                </div>
            @endif
        </div>
    </template>
</div>