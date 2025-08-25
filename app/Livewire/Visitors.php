<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\PeakVisitor;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Visitors extends Component
{
    /**
     * Collection of current visitors in the presence channel.
     *
     * @var Collection<int, array{id: string, type: string, ...}>
     */
    #[Locked]
    public Collection $visitors;

    /**
     * Total number of current visitors.
     */
    #[Locked]
    public int $totalVisitorCount = 0;

    /**
     * Number of authenticated users currently visiting.
     */
    #[Locked]
    public int $authUserCount = 0;

    /**
     * The highest number of concurrent visitors recorded.
     */
    #[Locked]
    public int $peakCount = 0;

    /**
     * The formatted date when the peak visitor count was recorded.
     */
    #[Locked]
    public string $peakDate = '';

    /**
     * Whether the WebSocket connection has failed.
     */
    #[Locked]
    public bool $connectionError = false;

    /**
     * Get the "visitors" collection, initializing it if needed.
     *
     * @return Collection<int, array{id: string, type: string, ...}>
     */
    private function getVisitors(): Collection
    {
        return $this->visitors ??= collect([]);
    }

    /**
     * Initialize the component when it mounts.
     */
    public function mount(): void
    {
        $this->initializePeakData();
    }

    /**
     * Initialize peak visitor data from the database.
     */
    private function initializePeakData(): void
    {
        $latestPeak = PeakVisitor::getPeak();
        if ($latestPeak !== null) {
            $this->peakCount = $latestPeak->count;
            $this->peakDate = $latestPeak->created_at->format('M j, Y');
        }
    }

    /**
     * Handle the initial visitor data when joining the presence channel.
     *
     * This method is called when the component first connects to the presence channel and receives the list of all
     * currently connected visitors.
     *
     * @param  array<int, array{id: string, type: string, ...}>  $visitors
     */
    #[On('echo-presence:visitors,here')]
    public function visitorsHere(array $visitors): void
    {
        $this->visitors = collect($visitors);

        $this->updateCounts();
        defer(fn () => $this->isPeak());
    }

    /**
     * Handle a new visitor joining the presence channel.
     *
     * This method is called when a new visitor connects to the presence channel. It adds the visitor to the local
     * collection if they aren't already present.
     *
     * @param  array{id: string, type: string, ...}  $visitor
     */
    #[On('echo-presence:visitors,joining')]
    public function visitorJoining($visitor): void
    {
        if ($this->getVisitors()->doesntContain('id', $visitor['id'])) {
            $this->getVisitors()->push($visitor);
        }

        $this->updateCounts();
        defer(fn () => $this->isPeak());
    }

    /**
     * Handle a visitor leaving the presence channel.
     *
     * This method is called when a visitor disconnects from the presence channel. It removes the visitor from the
     * local collection.
     *
     * @param  array{id: string, type: string, ...}  $visitor
     */
    #[On('echo-presence:visitors,leaving')]
    public function visitorLeaving($visitor): void
    {
        $this->visitors = $this->getVisitors()->reject(fn ($v): bool => $v['id'] === $visitor['id']);

        $this->updateCounts();
    }

    /**
     * Update the visitor count statistics.
     *
     * Recalculates the total visitor count and authenticated user count based on the current "visitors" collection.
     */
    private function updateCounts(): void
    {
        $this->totalVisitorCount = $this->getVisitors()->count();
        $this->authUserCount = $this->getVisitors()->filter(fn ($v): bool => $v['type'] === 'authenticated')->count();
    }

    /**
     * Check if the current visitor count represents a new peak.
     *
     * If the current visitor count exceeds the recorded peak, this method creates a new peak record and broadcasts
     * the update.
     */
    private function isPeak(): void
    {
        $current = $this->totalVisitorCount;
        $peak = PeakVisitor::getPeak()->count ?? 0;

        if ($current > $peak) {
            PeakVisitor::createPeak($current);
        }
    }

    /**
     * Handle peak visitor updates from the broadcast channel.
     *
     * This method listens for peak visitor updates broadcast from other parts of the application and updates the local
     * peak data accordingly.
     *
     * @param  array{count: int, date: string}  $data
     */
    #[On('echo:peak-visitors,PeakVisitorUpdated')]
    public function peakUpdated($data): void
    {
        $this->peakCount = $data['count'];
        $this->peakDate = $data['date'];
    }

    /**
     * Handle WebSocket connection errors from the frontend.
     */
    #[On('connection-error')]
    public function connectionError(): void
    {
        $this->connectionError = true;
    }

    /**
     * Handle WebSocket connection restoration from the frontend.
     */
    #[On('connection-restored')]
    public function connectionRestored(): void
    {
        $this->connectionError = false;
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.visitors');
    }
}
