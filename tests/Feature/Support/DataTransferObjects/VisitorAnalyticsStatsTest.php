<?php

declare(strict_types=1);

use App\Support\DataTransferObjects\VisitorAnalyticsStats;

/**
 * Build a stats payload with recognizable values for round-trip assertions.
 */
function visitorAnalyticsStatsFixture(): VisitorAnalyticsStats
{
    return new VisitorAnalyticsStats(
        totalEvents: 100,
        uniqueUsers: 40,
        authenticatedEvents: 70,
        anonymousEvents: 30,
        uniqueCountries: 5,
        topEvents: [['event_name' => 'login', 'count' => 60]],
        topBrowsers: [['browser' => 'Chrome', 'count' => 80]],
        topPlatforms: [['platform' => 'Windows', 'count' => 90]],
        topCountries: [['country_name' => 'Canada', 'country_code' => 'CA', 'count' => 50]],
        dailyEvents: [['date' => '2026-06-01', 'events' => 10]],
        computedAt: now()->getTimestamp(),
    );
}

it('round-trips through toArray and fromArray unchanged', function (): void {
    $stats = visitorAnalyticsStatsFixture();

    $rebuilt = VisitorAnalyticsStats::fromArray($stats->toArray());

    expect($rebuilt->toArray())->toBe($stats->toArray());
});

it('coerces missing and malformed payload data to safe defaults', function (): void {
    $stats = VisitorAnalyticsStats::fromArray([
        'total_events' => 'not-a-number',
        'top_events' => ['not-a-row', ['count' => '7']],
        'daily_events' => 'not-a-list',
    ]);

    expect($stats->totalEvents)->toBe(0)
        ->and($stats->uniqueUsers)->toBe(0)
        ->and($stats->topEvents)->toBe([['event_name' => '', 'count' => 7]])
        ->and($stats->dailyEvents)->toBe([]);
});

it('reports staleness based on the computed-at timestamp', function (): void {
    $stats = VisitorAnalyticsStats::fromArray([
        'computed_at' => now()->getTimestamp() - 1000,
    ]);

    expect($stats->isOlderThan(900))->toBeTrue()
        ->and($stats->isOlderThan(2000))->toBeFalse();
});
