<?php

declare(strict_types=1);

use App\Events\PeakVisitorUpdated;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component {
    /**
     * The current peak visitor count.
     */
    public int $peakCount = 0;

    /**
     * The date when the peak was reached.
     */
    public ?string $peakDate = null;

    /**
     * Initialize the component with current peak data.
     */
    public function mount(): void
    {
        // Load initial peak from database (cached for 5 minutes)
        $peakData = Cache::flexible('peak_visitor_data', [3, 5], function () {
            $peak = Visitor::getPeakStats();

            $date = null;
            if ($peak['count'] > 0 && $peak['date']) {
                $date = $peak['date']->format('M j, Y');
            }

            return [
                'count' => $peak['count'],
                'date' => $date,
            ];
        });

        $this->peakCount = $peakData['count'];
        $this->peakDate = $peakData['date'];
    }

    /**
     * Update the peak visitor count if the new count is higher.
     * This is called directly from AlpineJS when the presence channel detects a new peak.
     */
    public function updatePeak(int $count): void
    {
        // Use a mutex lock to prevent race conditions
        Cache::lock('peak-visitor-update', 5)->get(function () use ($count): void {
            // Get current peak from database
            $currentPeak = Visitor::getPeakStats();

            // Only update if the new count is actually higher
            if ($count > $currentPeak['count']) {
                // Update the peak in the database
                Visitor::updatePeak($count);

                // Clear the cache so next load gets fresh data
                Cache::forget('peak_visitor_data');

                // Broadcast the update to all clients
                broadcast(new PeakVisitorUpdated($count, now()->format('M j, Y')));

                // Update local component state
                $this->peakCount = $count;
                $this->peakDate = now()->format('M j, Y');
            }
        });
    }
};
?>

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
        </div>
    </template>
</div>
