<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Jobs\FetchCloudflareVisitorStatsJob;
use App\Models\ApiUsageMetric;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The footer's site statistics: how many people loaded a page recently, the all-time peak, and the open API's request
 * volume. Every figure is read from caches that scheduled jobs populate.
 */
final class SiteStats extends Component
{
    /**
     * The number of people who loaded a page within the trailing window, or null when no recent figure is cached.
     */
    public ?int $onlineCount;

    /**
     * The length in minutes of the window the online count covers.
     */
    public int $onlineWindowMinutes = 5;

    /**
     * The all-time peak online count.
     */
    public int $peakCount;

    /**
     * The formatted date on which the all-time peak was reached.
     */
    public ?string $peakDate;

    /**
     * The number of API requests served in the trailing 24 hours, counted at the origin.
     */
    public int $apiRequests24h;

    /**
     * The total API requests Cloudflare handled at the edge in the trailing 24 hours, or zero when Cloudflare analytics
     * are unavailable.
     */
    public int $apiEdgeRequests24h = 0;

    /**
     * The percentage of edge requests Cloudflare served from cache, or null when no edge data is available.
     */
    public ?int $apiCachedPct = null;

    public function __construct()
    {
        $this->readOnlineCount();
        $this->readPeak();
        $this->readApiUsage();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.site-stats');
    }

    /**
     * Read the online count a scheduled job caches from Cloudflare, leaving it null when no recent figure is cached.
     */
    private function readOnlineCount(): void
    {
        $visitors = Cache::get(FetchCloudflareVisitorStatsJob::CACHE_KEY);

        if (! is_array($visitors) || ! isset($visitors['count']) || ! is_int($visitors['count'])) {
            return;
        }

        $this->onlineCount = $visitors['count'];

        if (isset($visitors['window_minutes']) && is_int($visitors['window_minutes'])) {
            $this->onlineWindowMinutes = $visitors['window_minutes'];
        }
    }

    /**
     * Read the all-time peak through a short-lived cache.
     */
    private function readPeak(): void
    {
        $peakData = Cache::flexible('peak_visitor_data', [60, 120], function (): array {
            $peak = Visitor::getPeakStats();

            return [
                'count' => $peak['count'],
                'date' => $peak['count'] > 0 && $peak['date'] ? $peak['date']->format('M j, Y') : null,
            ];
        });

        $this->peakCount = $peakData['count'];
        $this->peakDate = $peakData['date'];
    }

    /**
     * Read the open API's request volume, preferring Cloudflare's edge total over the origin-only count.
     */
    private function readApiUsage(): void
    {
        // The sum is cached inside an array: the redis and database stores hand bare cached numerics back as strings.
        $apiUsage = Cache::flexible('api_requests_24h_v2', [300, 600], fn (): array => [
            'count' => ApiUsageMetric::requestsInLast24Hours(),
        ]);

        $this->apiRequests24h = $apiUsage['count'];

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
}
