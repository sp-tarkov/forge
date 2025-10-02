<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Events\PeakVisitorUpdated;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;

class VisitorTracker extends Component
{
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
                broadcast(new PeakVisitorUpdated(
                    $count,
                    now()->format('M j, Y')
                ));

                // Update local component state
                $this->peakCount = $count;
                $this->peakDate = now()->format('M j, Y');
            }
        });
    }

    /**
     * Render the visitor tracker view.
     */
    public function render(): View
    {
        return view('livewire.visitor-tracker');
    }
}
