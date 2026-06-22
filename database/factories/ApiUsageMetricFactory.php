<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiUsageMetric>
 */
final class ApiUsageMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestCount = fake()->numberBetween(1, 1000);

        return [
            'period' => ApiUsagePeriod::Minute,
            'period_start' => CarbonImmutable::now('UTC')->startOfMinute(),
            'route_name' => fake()->randomElement([
                'api.v0.mods',
                'api.v0.mods.show',
                'api.v0.mods.updates',
                'api.v0.addons',
            ]),
            'method' => 'GET',
            'status_code' => 200,
            'request_count' => $requestCount,
            'latency_sum_ms' => $requestCount * fake()->numberBetween(5, 200),
            // Park every request in a single histogram bucket so the counts stay internally consistent.
            ...$this->histogram(4, $requestCount),
        ];
    }

    /**
     * State for a coarse daily rollup row.
     */
    public function daily(): self
    {
        return $this->state(fn (): array => [
            'period' => ApiUsagePeriod::Day,
            'period_start' => CarbonImmutable::now('UTC')->startOfDay(),
        ]);
    }

    /**
     * Build the histogram columns with the entire count parked in a single bucket.
     *
     * @return array<string, int>
     */
    private function histogram(int $filledBucketIndex, int $count): array
    {
        $columns = [];

        foreach (range(0, 9) as $bucket) {
            $columns['lat_b'.$bucket] = $bucket === $filledBucketIndex ? $count : 0;
        }

        return $columns;
    }
}
