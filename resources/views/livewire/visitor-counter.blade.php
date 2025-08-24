<div
    wire:poll.15s="trackAndLoadStats"
    class="relative inline-flex flex-col items-end space-y-1 text-xs text-gray-400"
>
    <div class="flex items-center space-x-2">
        <div wire:loading wire:target="trackAndLoadStats">
            <svg class="animate-spin h-3 w-3 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <div class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
        </div>
        <div wire:loading.class="opacity-50 transition-opacity" wire:target="trackAndLoadStats">
            <span class="font-medium text-gray-300">{{ $currentTotal }}</span>
            <span>{{ Str::plural('user', $currentTotal) }} online</span>
            @if($currentAuthenticated > 0)
                <span class="text-gray-500">({{ $currentAuthenticated }} {{ Str::plural('member', $currentAuthenticated) }})</span>
            @endif
        </div>
    </div>

    @if($peakCount > 0 && $peakDate)
        <div class="ml-4" wire:loading.class="opacity-50 transition-opacity" wire:target="trackAndLoadStats">
            <span class="text-gray-500">Peak:</span>
            <span class="font-medium text-gray-400">{{ $peakCount }}</span>
            <span class="text-gray-500">on {{ $peakDate }}</span>
        </div>
    @endif
</div>
