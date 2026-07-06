<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Jobs\AggregateApiUsageDailyJob;
use App\Models\ApiUsageClient;
use App\Models\ApiUsageMetric;
use App\Models\ApiUsageUnmatchedRequest;

it('rolls the previous day minute rows into a single daily row', function (): void {
    $dayStart = now()->utc()->subDay()->startOfDay();

    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(3),
        'route_name' => 'api.v0.mods',
        'method' => 'GET',
        'status_code' => 200,
        'request_count' => 5,
        'latency_sum_ms' => 500,
    ]);
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(6),
        'route_name' => 'api.v0.mods',
        'method' => 'GET',
        'status_code' => 200,
        'request_count' => 3,
        'latency_sum_ms' => 300,
    ]);

    (new AggregateApiUsageDailyJob)->handle();

    $day = ApiUsageMetric::query()
        ->where('period', ApiUsagePeriod::Day->value)
        ->where('route_name', 'api.v0.mods')
        ->sole();

    expect($day->request_count)->toBe(8)
        ->and($day->latency_sum_ms)->toBe(800)
        ->and($day->period_start->equalTo($dayStart))->toBeTrue();
});

it('rolls the previous day client rows into daily top clients', function (): void {
    $dayStart = now()->utc()->subDay()->startOfDay();

    ApiUsageClient::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(1),
        'ip' => '203.0.113.7',
        'request_count' => 40,
    ]);
    ApiUsageClient::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(2),
        'ip' => '203.0.113.7',
        'request_count' => 60,
    ]);

    (new AggregateApiUsageDailyJob)->handle();

    expect((int) ApiUsageClient::query()
        ->where('period', ApiUsagePeriod::Day->value)
        ->where('ip', '203.0.113.7')
        ->value('request_count'))->toBe(100);
});

it('rolls the previous day unmatched rows into daily rows', function (): void {
    $dayStart = now()->utc()->subDay()->startOfDay();

    ApiUsageUnmatchedRequest::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(1),
        'path' => 'api/v0/nope',
        'method' => 'GET',
        'status_code' => 404,
        'request_count' => 4,
    ]);
    ApiUsageUnmatchedRequest::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => $dayStart->addHours(5),
        'path' => 'api/v0/nope',
        'method' => 'GET',
        'status_code' => 404,
        'request_count' => 6,
    ]);

    (new AggregateApiUsageDailyJob)->handle();

    $day = ApiUsageUnmatchedRequest::query()
        ->where('period', ApiUsagePeriod::Day->value)
        ->where('path', 'api/v0/nope')
        ->sole();

    expect($day->request_count)->toBe(10)
        ->and($day->period_start->equalTo($dayStart))->toBeTrue();
});

it('prunes unmatched rows past the retention window', function (): void {
    config(['api.usage.retention.minute_days' => 7]);

    $old = now()->utc()->subDays(10)->startOfMinute();

    ApiUsageUnmatchedRequest::factory()->create(['period_start' => $old]);

    (new AggregateApiUsageDailyJob)->handle();

    expect(ApiUsageUnmatchedRequest::query()->where('period_start', $old)->exists())->toBeFalse();
});

it('prunes minute rows past the retention window', function (): void {
    config(['api.usage.retention.minute_days' => 7]);

    $old = now()->utc()->subDays(10)->startOfMinute();
    $recent = now()->utc()->subDays(2)->startOfMinute();

    ApiUsageMetric::factory()->create(['period' => ApiUsagePeriod::Minute, 'period_start' => $old]);
    ApiUsageMetric::factory()->create(['period' => ApiUsagePeriod::Minute, 'period_start' => $recent]);

    (new AggregateApiUsageDailyJob)->handle();

    expect(ApiUsageMetric::query()->where('period_start', $old)->exists())->toBeFalse()
        ->and(ApiUsageMetric::query()->where('period_start', $recent)->exists())->toBeTrue();
});

it('prunes daily rows past the retention window', function (): void {
    config(['api.usage.retention.day_days' => 365]);

    $old = now()->utc()->subDays(400)->startOfDay();

    ApiUsageMetric::factory()->daily()->create(['period_start' => $old]);

    (new AggregateApiUsageDailyJob)->handle();

    expect(ApiUsageMetric::query()->where('period_start', $old)->exists())->toBeFalse();
});
