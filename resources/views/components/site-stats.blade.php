<div class="text-xs text-gray-400">
    {{-- Recent visitors, counted at the Cloudflare edge --}}
    @if ($onlineCount !== null)
        <div class="text-left sm:text-right">
            <span class="font-medium text-gray-300">{{ number_format($onlineCount) }}</span>
            {{ trans_choice('visitor|visitors', $onlineCount) }}
            <span class="text-gray-500">
                {{ __('in the last :minutes minutes', ['minutes' => $onlineWindowMinutes]) }}
            </span>
        </div>
    @endif

    {{-- All-time peak --}}
    @if ($peakCount > 0 && $peakDate)
        <div class="text-left sm:text-right">
            <span class="text-gray-500">{{ __('Peak:') }}</span>
            <span class="font-medium text-gray-400">{{ number_format($peakCount) }}</span>
            <span class="text-gray-500">{{ __('on :date', ['date' => $peakDate]) }}</span>
        </div>
    @endif

    {{-- API requests served in the last 24 hours --}}
    @if ($apiEdgeRequests24h > 0)
        <div class="mt-1 text-left sm:text-right">
            <span class="font-medium text-gray-400">{{ number_format($apiEdgeRequests24h) }}</span>
            <span class="text-gray-500">{{ __('API requests in the last 24h') }}</span>
        </div>
        @if ($apiCachedPct !== null)
            <div class="text-left text-gray-500 sm:text-right">
                {{ $apiCachedPct }}% {{ __('served from cache') }}
            </div>
        @endif
    @elseif ($apiRequests24h > 0)
        <div class="mt-1 text-left sm:text-right">
            <span class="font-medium text-gray-400">{{ number_format($apiRequests24h) }}</span>
            <span class="text-gray-500">{{ __('API requests in the last 24h') }}</span>
        </div>
    @endif
</div>
