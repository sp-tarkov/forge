<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Visitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class VisitorCounter extends Component
{
    /**
     * Total number of currently active visitors.
     */
    public int $currentTotal = 0;

    /**
     * Number of authenticated active visitors.
     */
    public int $currentAuthenticated = 0;

    /**
     * Historical peak number of concurrent visitors.
     */
    public int $peakCount = 0;

    /**
     * Date when the peak visitor count was reached.
     */
    public ?string $peakDate = null;

    /**
     * Initialize the component and load initial statistics.
     */
    public function mount(): void
    {
        $this->trackAndLoadStats();
    }

    /**
     * Track the current visitor and load updated statistics.
     */
    public function trackAndLoadStats(): void
    {
        // Track the current visitor
        $sessionId = session()->getId();
        if ($sessionId) {
            Visitor::trackVisitor($sessionId, Auth::id());
        }

        // Load current visitor statistics
        $currentStats = Visitor::getCurrentStats();
        $this->currentTotal = $currentStats['total'];
        $this->currentAuthenticated = $currentStats['authenticated'];

        // Load peak statistics
        $peakStats = Visitor::getPeakStats();
        $this->peakCount = $peakStats['count'];
        $this->peakDate = $peakStats['date']?->format('M j, Y');
    }

    /**
     * Render the visitor counter view.
     */
    public function render(): View
    {
        return view('livewire.visitor-counter');
    }
}
