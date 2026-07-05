<?php

declare(strict_types=1);

use App\Contracts\VisitorPresenceStore;
use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Models\ApiUsageMetric;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

use function Illuminate\Support\defer;

new class extends Component
{
    /**
     * The heartbeat poll interval in seconds.
     */
    #[Locked]
    public int $heartbeatSeconds = 60;

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
     * The number of API requests served in the trailing 24 hours, counted at the origin. Used as the fallback display
     * when Cloudflare edge totals are unavailable.
     */
    #[Locked]
    public int $apiRequests24h = 0;

    /**
     * The total API requests Cloudflare handled at the edge in the trailing 24 hours, including those served from cache
     * that never reached the origin. Zero when Cloudflare analytics are unavailable.
     */
    #[Locked]
    public int $apiEdgeRequests24h = 0;

    /**
     * The percentage of edge requests that Cloudflare served from cache, or null when no edge data is available.
     */
    #[Locked]
    public ?int $apiCachedPct = null;

    /**
     * Load the stats for the initial render.
     */
    public function mount(VisitorPresenceStore $presence): void
    {
        $this->heartbeatSeconds = max(10, intdiv(config()->integer('visitors.online_window'), 3));

        $this->refreshStats($presence);
    }

    /**
     * Refresh the online count, peak, and API usage. Runs on mount and on every heartbeat poll; the poll request
     * itself re-records the visitor's presence through the middleware, keeping idle open tabs counted.
     */
    public function refreshStats(VisitorPresenceStore $presence): void
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

        // Prefer the Cloudflare edge total when a recent fetch is cached: it counts the API requests Cloudflare served
        // from cache too, which never reach the origin counters above. A scheduled job populates this key, so the read
        // here is pure cache and never makes an external call on a footer render. When the key is absent the footer
        // falls back to the origin-only count.
        $edgeUsage = Cache::get(FetchCloudflareApiAnalyticsJob::CACHE_KEY);

        if (is_array($edgeUsage)
            && isset($edgeUsage['edge_total'], $edgeUsage['cached_pct'])
            && is_int($edgeUsage['edge_total'])
            && is_numeric($edgeUsage['cached_pct'])
            && $edgeUsage['edge_total'] > 0
        ) {
            $this->apiEdgeRequests24h = $edgeUsage['edge_total'];
            $this->apiCachedPct = (int) round((float) $edgeUsage['cached_pct']);
        }
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
