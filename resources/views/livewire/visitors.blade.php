<div class="text-right">
    @if($connectionError)
        {{-- Error state when WebSocket connection fails --}}
        <div class="flex items-center justify-end space-x-2">
            <div class="relative flex h-2 w-2">
                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
            </div>
            <div class="text-xs text-gray-400">
                <span class="text-red-400">Connection error</span>
            </div>
        </div>
    @else
        {{-- Normal state with visitor count --}}
        <div class="flex items-center justify-end space-x-2">
            <div wire:loading wire:target="updateCounts">
                <svg class="animate-spin h-3 w-3 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
            </div>
            <div wire:loading.class="opacity-50 transition-opacity" wire:target="updateCounts" class="text-xs text-gray-400">
                <span class="font-medium">{{ $totalVisitorCount }}</span>
                <span>{{ Str::plural('user', $totalVisitorCount) }} currently online</span>
                @if($authUserCount > 0)
                    <span class="text-gray-500">({{ $authUserCount }} {{ Str::plural('member', $authUserCount) }})</span>
                @endif
            </div>
        </div>
        @if($peakCount > 0 && $peakDate)
            <div class="text-xs text-gray-400 mt-1" wire:loading.class="opacity-50 transition-opacity" wire:target="isPeak">
                <span class="text-gray-500">Peak:</span>
                <span class="font-medium">{{ $peakCount }}</span>
                <span class="text-gray-500">on {{ $peakDate }}</span>
            </div>
        @endif
    @endif
</div>
