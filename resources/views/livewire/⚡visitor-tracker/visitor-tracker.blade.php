{{-- Presence heartbeat; keep-alive keeps background tabs polling. --}}
<div class="text-xs text-gray-400" wire:poll.{{ $heartbeatSeconds }}s.keep-alive="refreshStats">
    <div>
        {{-- Online count --}}
        <div class="flex items-center justify-start sm:justify-end space-x-2">
            <div class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
            </div>
            <div>
                <span class="font-medium text-gray-300">{{ $onlineCount }}</span>
                {{ $onlineCount === 1 ? 'user currently online' : 'users currently online' }}
                @if ($memberCount > 0)
                    <span class="text-gray-500">({{ $memberCount }} {{ $memberCount === 1 ? 'member' : 'members' }})</span>
                @endif
            </div>
        </div>

        {{-- Peak display --}}
        @if ($peakCount > 0 && $peakDate)
            <div class="text-left sm:text-right mt-1">
                <span class="text-gray-500">Peak:</span>
                <span class="font-medium text-gray-400">{{ $peakCount }}</span>
                <span class="text-gray-500">on {{ $peakDate }}</span>
            </div>
        @endif

        {{-- API requests served in the last 24 hours --}}
        @if ($apiEdgeRequests24h > 0)
            <div class="text-left sm:text-right mt-1">
                <span class="font-medium text-gray-400">{{ number_format($apiEdgeRequests24h) }}</span>
                <span class="text-gray-500">{{ __('API requests in the last 24h') }}</span>
            </div>
            @if ($apiCachedPct !== null)
                <div class="text-left sm:text-right text-gray-500">
                    {{ $apiCachedPct }}% {{ __('served from Cloudflare cache') }}
                </div>
            @endif
        @elseif ($apiRequests24h > 0)
            <div class="text-left sm:text-right mt-1">
                <span class="font-medium text-gray-400">{{ number_format($apiRequests24h) }}</span>
                <span class="text-gray-500">{{ __('API requests in the last 24h') }}</span>
            </div>
        @endif
    </div>
</div>
