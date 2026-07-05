<?php

declare(strict_types=1);

use App\Jobs\ComputeVisitorAnalyticsStatsJob;
use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use App\Support\DataTransferObjects\VisitorAnalyticsStats;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Build a minimal stats payload with the given values for cache seeding.
 */
function visitorAnalyticsComponentStats(int $totalEvents, int $computedAt): VisitorAnalyticsStats
{
    return new VisitorAnalyticsStats(
        totalEvents: $totalEvents,
        uniqueUsers: 0,
        authenticatedEvents: 0,
        anonymousEvents: 0,
        uniqueCountries: 0,
        topEvents: [],
        topBrowsers: [],
        topPlatforms: [],
        topCountries: [],
        dailyEvents: [],
        computedAt: $computedAt,
    );
}

it('queues a stats job and shows the queued state on a cold cache', function (): void {
    Queue::fake();

    Livewire::withoutLazyLoading()
        ->test('admin.visitor-analytics-stats')
        ->assertSee('Queued for analysis');

    Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);
});

it('renders cached stats immediately without queueing a refresh', function (): void {
    $service = resolve(VisitorAnalyticsService::class);
    $service->storeStats(new VisitorAnalyticsFilters, visitorAnalyticsComponentStats(4321, now()->getTimestamp()));

    Queue::fake();

    Livewire::withoutLazyLoading()
        ->test('admin.visitor-analytics-stats')
        ->assertSee('4,321')
        ->assertDontSee('Queued for analysis');

    Queue::assertNothingPushed();
});

it('shows the failure state and retries with a fresh run', function (): void {
    Queue::fake();

    resolve(VisitorAnalyticsService::class)->markFailed(new VisitorAnalyticsFilters, 'boom');

    Livewire::withoutLazyLoading()
        ->test('admin.visitor-analytics-stats')
        ->assertSee('The analytics computation failed.')
        ->assertSee('boom')
        ->call('retryStats')
        ->assertSee('Queued for analysis');

    Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);
});

it('re-queues the run when its state expires without producing stats', function (): void {
    Queue::fake();

    $component = Livewire::withoutLazyLoading()->test('admin.visitor-analytics-stats');

    Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);

    $this->travel(11)->minutes();

    $component->call('checkStats');

    Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 2);
});
