<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\PeakVisitor;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class VisitorTracker extends Component
{
    public int $peakCount;
    public string $peakDate;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        // Cache the peak visitor data for 5 minutes to reduce database queries
        $peakData = Cache::remember('peak_visitor_data', 300, function () {
            $peak = PeakVisitor::getPeak();
            return [
                'count' => $peak !== null ? $peak->count : 0,
                'date' => $peak !== null ? $peak->created_at->format('M j, Y') : '',
            ];
        });

        $this->peakCount = $peakData['count'];
        $this->peakDate = $peakData['date'];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.visitor-tracker');
    }
}
