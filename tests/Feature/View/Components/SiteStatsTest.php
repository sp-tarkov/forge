<?php

declare(strict_types=1);

use App\Jobs\FetchCloudflareApiAnalyticsJob;
use App\Jobs\FetchCloudflareVisitorStatsJob;
use App\Models\Visitor;
use App\View\Components\SiteStats;
use Illuminate\Support\Facades\Cache;

it('shows the cached visitor count and its window', function (): void {
    Cache::put(FetchCloudflareVisitorStatsJob::CACHE_KEY, ['count' => 694, 'window_minutes' => 5]);

    $component = new SiteStats;

    expect($component->onlineCount)->toBe(694)
        ->and($component->onlineWindowMinutes)->toBe(5);
});

it('leaves the visitor count null when nothing is cached', function (): void {
    expect((new SiteStats)->onlineCount)->toBeNull();
});

it('ignores a malformed cached payload', function (): void {
    Cache::put(FetchCloudflareVisitorStatsJob::CACHE_KEY, ['count' => 'many']);

    expect((new SiteStats)->onlineCount)->toBeNull();
});

it('reads the stored peak and formats its date', function (): void {
    Visitor::query()->create(['peak_count' => 1234, 'peak_date' => now()->parse('2026-03-09')]);

    $component = new SiteStats;

    expect($component->peakCount)->toBe(1234)
        ->and($component->peakDate)->toBe('Mar 9, 2026');
});

it('reports no peak before one has been recorded', function (): void {
    $component = new SiteStats;

    expect($component->peakCount)->toBe(0)
        ->and($component->peakDate)->toBeNull();
});

it('prefers the Cloudflare edge total over the origin count', function (): void {
    Cache::put(FetchCloudflareApiAnalyticsJob::CACHE_KEY, [
        'edge_total' => 5000, 'cached' => 4000, 'origin' => 1000, 'cached_pct' => 80.0,
    ]);

    $component = new SiteStats;

    expect($component->apiEdgeRequests24h)->toBe(5000)
        ->and($component->apiCachedPct)->toBe(80);
});

it('falls back to the origin count when no edge total is cached', function (): void {
    $component = new SiteStats;

    expect($component->apiEdgeRequests24h)->toBe(0)
        ->and($component->apiCachedPct)->toBeNull();
});

it('renders inside a real page response', function (): void {
    Cache::put(FetchCloudflareVisitorStatsJob::CACHE_KEY, ['count' => 694, 'window_minutes' => 5]);
    Visitor::query()->create(['peak_count' => 1234, 'peak_date' => now()->parse('2026-03-09')]);

    $this->get('/community-standards')
        ->assertOk()
        ->assertSee('694')
        ->assertSee('in the last 5 minutes')
        ->assertSee('1,234')
        ->assertDontSee('visitor-tracker');
});

it('renders the footer markup without touching the database or network', function (): void {
    Cache::put(FetchCloudflareVisitorStatsJob::CACHE_KEY, ['count' => 694, 'window_minutes' => 5]);
    Cache::put(FetchCloudflareApiAnalyticsJob::CACHE_KEY, [
        'edge_total' => 5000, 'cached' => 4000, 'origin' => 1000, 'cached_pct' => 80.0,
    ]);
    Visitor::query()->create(['peak_count' => 1234, 'peak_date' => now()->parse('2026-03-09')]);

    $rendered = (string) view('components.site-stats', (new SiteStats)->data());

    expect($rendered)->toContain('694')
        ->toContain('in the last 5 minutes')
        ->toContain('1,234')
        ->toContain('Mar 9, 2026')
        ->toContain('80% served from cache')
        ->not->toContain('Cloudflare cache');
});
