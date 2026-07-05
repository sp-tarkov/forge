<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Jobs\ComputeVisitorAnalyticsStatsJob;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;
use App\Support\DataTransferObjects\VisitorAnalyticsStats;
use Illuminate\Support\Facades\Queue;

/**
 * Build a minimal stats payload with the given values for cache seeding.
 */
function visitorAnalyticsServiceStats(int $totalEvents, int $computedAt): VisitorAnalyticsStats
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

/**
 * Create a login tracking event with deterministic attributes.
 *
 * @param  array<string, mixed>  $attributes
 */
function visitorAnalyticsServiceEvent(array $attributes): TrackingEvent
{
    return TrackingEvent::factory()
        ->eventType(TrackingEventType::LOGIN)
        ->create([
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'country_code' => 'CA',
            'country_name' => 'Canada',
            'created_at' => now()->subDay(),
            ...$attributes,
        ]);
}

describe('VisitorAnalyticsService stats computation', function (): void {
    it('computes aggregate statistics for the filtered range', function (): void {
        $user = User::factory()->create();

        visitorAnalyticsServiceEvent(['visitor_type' => User::class, 'visitor_id' => $user->id, 'ip' => '10.0.0.1']);
        visitorAnalyticsServiceEvent(['visitor_type' => null, 'visitor_id' => null, 'ip' => '10.0.0.1']);
        visitorAnalyticsServiceEvent(['visitor_type' => null, 'visitor_id' => null, 'ip' => '10.0.0.2']);

        $stats = resolve(VisitorAnalyticsService::class)->computeStats(new VisitorAnalyticsFilters);

        expect($stats->totalEvents)->toBe(3)
            ->and($stats->authenticatedEvents)->toBe(1)
            ->and($stats->anonymousEvents)->toBe(2)
            ->and($stats->uniqueUsers)->toBe(2)
            ->and($stats->uniqueCountries)->toBe(1)
            ->and($stats->topEvents)->toBe([['event_name' => 'login', 'count' => 3]])
            ->and($stats->topBrowsers)->toBe([['browser' => 'Chrome', 'count' => 3]])
            ->and($stats->topCountries)->toBe([['country_name' => 'Canada', 'country_code' => 'CA', 'count' => 3]])
            ->and($stats->dailyEvents)->toBe([['date' => now()->subDay()->format('Y-m-d'), 'events' => 3]])
            ->and($stats->computedAt)->toBe(now()->getTimestamp());
    });

    it('keeps the user search inside the other active filters', function (): void {
        $alice = User::factory()->create(['name' => 'Wanted Alice', 'email' => 'walice@example.test']);
        $bob = User::factory()->create(['name' => 'Innocent Bob', 'email' => 'ibob@example.test']);

        visitorAnalyticsServiceEvent([
            'visitor_type' => User::class,
            'visitor_id' => $alice->id,
            'ip' => '10.0.0.1',
            'created_at' => '2026-06-15 12:00:00',
        ]);
        visitorAnalyticsServiceEvent([
            'visitor_type' => User::class,
            'visitor_id' => $alice->id,
            'ip' => '10.0.0.1',
            'created_at' => '2026-05-01 12:00:00',
        ]);
        visitorAnalyticsServiceEvent([
            'visitor_type' => User::class,
            'visitor_id' => $bob->id,
            'ip' => '10.0.0.2',
            'created_at' => '2026-06-16 12:00:00',
        ]);

        $stats = resolve(VisitorAnalyticsService::class)->computeStats(new VisitorAnalyticsFilters(
            userSearch: 'Wanted Alice',
            dateFrom: '2026-06-01',
            dateTo: '2026-06-30',
        ));

        expect($stats->totalEvents)->toBe(1);
    });

    it('matches a numeric user search against the exact visitor id', function (): void {
        $user = User::factory()->create(['name' => 'Nameless', 'email' => 'nameless@example.test']);

        visitorAnalyticsServiceEvent(['visitor_type' => User::class, 'visitor_id' => $user->id, 'ip' => '10.0.0.1']);

        $service = resolve(VisitorAnalyticsService::class);

        $matched = $service->computeStats(new VisitorAnalyticsFilters(userSearch: (string) $user->id));
        $unmatched = $service->computeStats(new VisitorAnalyticsFilters(userSearch: '999999'));

        expect($matched->totalEvents)->toBe(1)
            ->and($unmatched->totalEvents)->toBe(0);
    });

    it('filters by the referer column', function (): void {
        visitorAnalyticsServiceEvent(['ip' => '10.0.0.1', 'referer' => 'https://www.google.com/search?q=forge']);
        visitorAnalyticsServiceEvent(['ip' => '10.0.0.2', 'referer' => 'https://bing.com/search']);

        $stats = resolve(VisitorAnalyticsService::class)->computeStats(new VisitorAnalyticsFilters(referer: 'google.com'));

        expect($stats->totalEvents)->toBe(1);
    });
});

describe('VisitorAnalyticsService run orchestration', function (): void {
    it('queues a single stats job for a cold cache', function (): void {
        Queue::fake();

        $service = resolve(VisitorAnalyticsService::class);
        $filters = new VisitorAnalyticsFilters;

        $service->ensureStatsAvailable($filters);
        $service->ensureStatsAvailable($filters);

        Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);

        expect($service->getRunState($filters))->toBe(['status' => VisitorAnalyticsService::STATUS_PENDING, 'error' => null]);
    });

    it('serves stale stats while queueing a background refresh', function (): void {
        $service = resolve(VisitorAnalyticsService::class);
        $filters = new VisitorAnalyticsFilters;

        $service->storeStats($filters, visitorAnalyticsServiceStats(7, now()->getTimestamp() - 1000));

        Queue::fake();
        $service->ensureStatsAvailable($filters);

        Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);

        expect($service->getStats($filters)?->totalEvents)->toBe(7);
    });

    it('does not queue a refresh while the cached stats are fresh', function (): void {
        $service = resolve(VisitorAnalyticsService::class);
        $filters = new VisitorAnalyticsFilters;

        $service->storeStats($filters, visitorAnalyticsServiceStats(7, now()->getTimestamp()));

        Queue::fake();
        $service->ensureStatsAvailable($filters);

        Queue::assertNothingPushed();
    });

    it('stores stats and clears the run state', function (): void {
        $service = resolve(VisitorAnalyticsService::class);
        $filters = new VisitorAnalyticsFilters;

        $service->markProcessing($filters);
        $service->storeStats($filters, visitorAnalyticsServiceStats(12, now()->getTimestamp()));

        expect($service->getStats($filters)?->totalEvents)->toBe(12)
            ->and($service->getRunState($filters))->toBeNull();
    });

    it('records failures and retries with a fresh run', function (): void {
        $service = resolve(VisitorAnalyticsService::class);
        $filters = new VisitorAnalyticsFilters;

        $service->markFailed($filters, 'boom');

        expect($service->getRunState($filters))->toBe(['status' => VisitorAnalyticsService::STATUS_FAILED, 'error' => 'boom']);

        Queue::fake();
        $service->retry($filters);

        Queue::assertPushed(ComputeVisitorAnalyticsStatsJob::class, 1);

        expect($service->getRunState($filters))->toBe(['status' => VisitorAnalyticsService::STATUS_PENDING, 'error' => null]);
    });
});
