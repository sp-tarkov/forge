<?php

declare(strict_types=1);

namespace App\Enums\Api\V0;

/**
 * Fixed-boundary latency buckets for API usage tracking.
 *
 * Rather than storing a timing for every request, the recorder increments one of these buckets per request. The
 * histogram is inexpensive to maintain (a single counter increment) and bounded in size, and it lets the admin
 * dashboard estimate percentiles (e.g. p95) without ever holding per-request data. Each case maps 1:1 to a column on
 * the `api_usage_metrics` table, so this enum is the single source of truth for both writing and reading the histogram.
 */
enum ApiLatencyBucket: string
{
    case B0 = 'lat_b0';
    case B1 = 'lat_b1';
    case B2 = 'lat_b2';
    case B3 = 'lat_b3';
    case B4 = 'lat_b4';
    case B5 = 'lat_b5';
    case B6 = 'lat_b6';
    case B7 = 'lat_b7';
    case B8 = 'lat_b8';
    case B9 = 'lat_b9';

    /**
     * Inclusive upper bound, in milliseconds, for each bucket in case order. The final bucket is unbounded (it captures
     * everything slower than the previous boundary), represented by null.
     *
     * @var array<int, int|null>
     */
    private const array UPPER_BOUNDS_MS = [5, 10, 25, 50, 100, 250, 500, 1000, 2500, null];

    /**
     * Resolve the bucket latency falls into.
     */
    public static function forLatency(float $milliseconds): self
    {
        foreach (self::cases() as $bucket) {
            $upperBound = $bucket->upperBoundMs();

            if ($upperBound === null || $milliseconds <= $upperBound) {
                return $bucket;
            }
        }

        return self::B9;
    }

    /**
     * All histogram column names in bucket order.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_map(static fn (self $bucket): string => $bucket->column(), self::cases());
    }

    /**
     * Estimate percentile latency (in milliseconds) from a set of histogram bucket counts.
     *
     * Walks the cumulative distribution and returns the upper bound of the bucket the target rank falls into. For the
     * unbounded overflow bucket the previous boundary is returned as a conservative floor (the true value is only
     * known to be at least that large). Returns null when there is no data.
     *
     * @param  array<string, int>  $counts  Bucket counts keyed by column name (lat_b0..lat_b9).
     */
    public static function estimatePercentileMs(array $counts, int $total, float $percentile): ?int
    {
        if ($total <= 0) {
            return null;
        }

        $targetRank = (int) ceil(($percentile / 100) * $total);
        $cumulative = 0;

        foreach (self::cases() as $bucket) {
            $cumulative += $counts[$bucket->column()] ?? 0;

            if ($cumulative >= $targetRank) {
                return $bucket->upperBoundMs() ?? self::UPPER_BOUNDS_MS[$bucket->index() - 1];
            }
        }

        return null;
    }

    /**
     * The zero-based position of this bucket within the histogram.
     */
    public function index(): int
    {
        return (int) str_replace('lat_b', '', $this->value);
    }

    /**
     * The database column that stores this bucket's count.
     */
    public function column(): string
    {
        return $this->value;
    }

    /**
     * The inclusive upper bound for this bucket in milliseconds, or null for the unbounded overflow bucket.
     */
    public function upperBoundMs(): ?int
    {
        return self::UPPER_BOUNDS_MS[$this->index()];
    }
}
