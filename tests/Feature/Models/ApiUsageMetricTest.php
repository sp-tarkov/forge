<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiLatencyBucket;
use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageMetric;
use Carbon\CarbonImmutable;

it('casts its attributes', function (): void {
    $metric = ApiUsageMetric::factory()->create();

    expect($metric->period)->toBeInstanceOf(ApiUsagePeriod::class)
        ->and($metric->period_start)->toBeInstanceOf(CarbonImmutable::class)
        ->and($metric->request_count)->toBeInt()
        ->and($metric->lat_b0)->toBeInt();
});

it('computes the average latency', function (): void {
    $metric = ApiUsageMetric::factory()->create(['request_count' => 4, 'latency_sum_ms' => 200]);

    expect($metric->averageLatencyMs())->toBe(50.0);
});

it('returns a null average latency without requests', function (): void {
    $metric = ApiUsageMetric::factory()->create(['request_count' => 0, 'latency_sum_ms' => 0]);

    expect($metric->averageLatencyMs())->toBeNull();
});

it('exposes the histogram keyed by column', function (): void {
    $metric = ApiUsageMetric::factory()->create();

    expect($metric->histogram())->toHaveKeys(['lat_b0', 'lat_b4', 'lat_b9'])
        ->and(array_sum($metric->histogram()))->toBe($metric->request_count);
});

it('builds a sum projection covering every additive column', function (): void {
    // Rebuild the projection from the enum and assert the vetted literal matches it exactly, so adding, removing,
    // renaming, or reordering a latency bucket forces this literal to be updated in step.
    $expected = collect(['request_count', 'latency_sum_ms'])
        ->merge(ApiLatencyBucket::columns())
        ->map(fn (string $column): string => sprintf('SUM(%s) as %s', $column, $column))
        ->implode(', ');

    expect(ApiUsageMetric::sumSelect())->toBe($expected);
});

it('sums request counts served in the last 24 hours', function (): void {
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => now()->utc()->subHours(2),
        'request_count' => 100,
    ]);
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => now()->utc()->subHours(23),
        'request_count' => 50,
    ]);
    // Older than 24 hours: excluded.
    ApiUsageMetric::factory()->create([
        'period' => ApiUsagePeriod::Minute,
        'period_start' => now()->utc()->subDays(2),
        'request_count' => 999,
    ]);
    // Daily rollup row: excluded (wrong granularity).
    ApiUsageMetric::factory()->daily()->create([
        'period_start' => now()->utc()->startOfDay(),
        'request_count' => 999,
    ]);

    expect(ApiUsageMetric::requestsInLast24Hours())->toBe(150);
});
