<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Jobs\ComputeVisitorAnalyticsStatsJob;
use App\Models\TrackingEvent;
use App\Services\VisitorAnalyticsService;
use App\Support\DataTransferObjects\VisitorAnalyticsFilters;

it('computes and stores the stats payload for its filters', function (): void {
    TrackingEvent::factory()
        ->eventType(TrackingEventType::LOGIN)
        ->count(2)
        ->create(['created_at' => now()->subDay()]);

    $filters = new VisitorAnalyticsFilters;

    new ComputeVisitorAnalyticsStatsJob($filters)->handle(resolve(VisitorAnalyticsService::class));

    $service = resolve(VisitorAnalyticsService::class);

    expect($service->getStats($filters)?->totalEvents)->toBe(2)
        ->and($service->getRunState($filters))->toBeNull();
});

it('records the failure reason when the job fails', function (): void {
    $filters = new VisitorAnalyticsFilters;

    new ComputeVisitorAnalyticsStatsJob($filters)->failed(new RuntimeException('boom'));

    expect(resolve(VisitorAnalyticsService::class)->getRunState($filters))
        ->toBe(['status' => VisitorAnalyticsService::STATUS_FAILED, 'error' => 'boom']);
});

it('runs on the configured visitor analytics queue', function (): void {
    $job = new ComputeVisitorAnalyticsStatsJob(new VisitorAnalyticsFilters);

    expect($job->queue)->toBe('visitor-analytics');
});
