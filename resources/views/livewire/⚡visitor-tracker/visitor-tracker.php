<?php

declare(strict_types=1);

use App\Contracts\VisitorPresenceStore;
use App\Models\ApiUsageMetric;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

use function Illuminate\Support\defer;

new class extends Component
{
    /**
     * The number of visitors currently online.
     */
    #[Locked]
    public int $onlineCount = 0;

    /**
     * How many of the online visitors are authenticated members.
     */
    #[Locked]
    public int $memberCount = 0;

    /**
     * The all-time peak visitor count.
     */
    #[Locked]
    public int $peakCount = 0;

    /**
     * The date when the all-time peak was reached.
     */
    #[Locked]
    public ?string $peakDate = null;

    /**
     * The number of API requests served in the trailing 24 hours.
     */
    #[Locked]
    public int $apiRequests24h = 0;

    /**
     * Render the current online count, peak, and API usage. The component re-mounts on every navigation, so these
     * values refresh as a visitor browses without any polling or WebSocket connection.
     */
    public function mount(VisitorPresenceStore $presence): void
    {
        // The presence read is exact for the live window; cache it for a few seconds so bursts of footer renders share
        // one Redis read rather than one each. A plain array round-trips safely through the cache.
        $counts = Cache::flexible('online_visitor_counts', [10, 15], fn (): array => $presence->counts());

        $this->onlineCount = $counts['total'];
        $this->memberCount = $counts['members'];

        // Load the all-time peak from the database (cached for a few seconds).
        $peakData = Cache::flexible('peak_visitor_data', [3, 5], function (): array {
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

        $this->refreshPeakIfExceeded();

        // Summing a day of per-minute rollup rows is too heavy to run on every footer render, so cache it. Wrapped in
        // an array (not cached as a bare int) because the redis and database stores keep bare numeric values
        // un-serialized and hand them back as strings, which would break the typed property; an array round-trips
        // through serialization with its int intact. The `_v2` key suffix avoids colliding with an earlier release
        // that cached a bare int under the un-suffixed key.
        $apiUsage = Cache::flexible('api_requests_24h_v2', [300, 600], fn (): array => [
            'count' => ApiUsageMetric::requestsInLast24Hours(),
        ]);

        $this->apiRequests24h = $apiUsage['count'];
    }

    /**
     * When the live total beats the stored peak, show the new peak immediately and persist it after the response.
     *
     * The render only does an integer comparison; the database write is deferred so it never delays the response. A
     * mutex guards against concurrent workers racing to set the peak, and the inner re-check keeps the value monotonic.
     */
    private function refreshPeakIfExceeded(): void
    {
        if ($this->onlineCount <= $this->peakCount) {
            return;
        }

        $count = $this->onlineCount;

        $this->peakCount = $count;
        $this->peakDate = now()->format('M j, Y');

        defer(function () use ($count): void {
            Cache::lock('peak-visitor-update', 5)->get(function () use ($count): void {
                if ($count > Visitor::getPeakStats()['count']) {
                    Visitor::updatePeak($count);
                    Cache::forget('peak_visitor_data');
                }
            });
        });
    }
};
