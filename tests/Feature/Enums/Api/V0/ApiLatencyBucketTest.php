<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiLatencyBucket;

/**
 * Build a full histogram counts array from a sparse map of column => count.
 *
 * @param  array<string, int>  $sparse
 * @return array<string, int>
 */
function histogramCounts(array $sparse): array
{
    return array_merge(array_fill_keys(ApiLatencyBucket::columns(), 0), $sparse);
}

it('maps a latency to the correct bucket', function (float $milliseconds, string $column): void {
    expect(ApiLatencyBucket::forLatency($milliseconds)->column())->toBe($column);
})->with([
    'lower edge' => [0.0, 'lat_b0'],
    'first boundary' => [5.0, 'lat_b0'],
    'just over first boundary' => [5.1, 'lat_b1'],
    'mid range' => [42.0, 'lat_b3'],
    'last boundary' => [2500.0, 'lat_b8'],
    'overflow' => [9000.0, 'lat_b9'],
]);

it('exposes ten ordered histogram columns', function (): void {
    expect(ApiLatencyBucket::columns())->toBe([
        'lat_b0', 'lat_b1', 'lat_b2', 'lat_b3', 'lat_b4', 'lat_b5', 'lat_b6', 'lat_b7', 'lat_b8', 'lat_b9',
    ]);
});

it('interpolates a percentile within the bucket it lands in', function (array $sparse, int $total, int $percentile, int $expected): void {
    expect(ApiLatencyBucket::estimatePercentileMs(histogramCounts($sparse), $total, $percentile))->toBe($expected);
})->with([
    // Rank 95 of 95 in the 50-100ms bucket sits at the bucket's upper bound.
    'rank at the top of a bucket' => [['lat_b4' => 95, 'lat_b9' => 5], 100, 95, 100],
    // Rank 95 is the 45th of 50 counts in the 100-250ms bucket: 100 + (45/50) * 150.
    'rank partway through a bucket' => [['lat_b4' => 50, 'lat_b5' => 50], 100, 95, 235],
    // Rank 50 is the 50th of 100 counts in the 100-250ms bucket: 100 + (50/100) * 150.
    'rank midway through a bucket' => [['lat_b5' => 100], 100, 50, 175],
    // The first bucket interpolates from zero: 0 + (50/100) * 5, rounded.
    'rank in the first bucket' => [['lat_b0' => 100], 100, 50, 3],
]);

it('returns the previous boundary when the percentile lands in the overflow bucket', function (): void {
    $counts = histogramCounts(['lat_b0' => 90, 'lat_b9' => 10]);

    expect(ApiLatencyBucket::estimatePercentileMs($counts, 100, 95))->toBe(2500);
});

it('returns null when there is no data', function (): void {
    expect(ApiLatencyBucket::estimatePercentileMs([], 0, 95))->toBeNull();
});
