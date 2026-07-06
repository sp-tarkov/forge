<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageUnmatchedRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiUsageUnmatchedRequest>
 */
final class ApiUsageUnmatchedRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => ApiUsagePeriod::Minute,
            'period_start' => CarbonImmutable::now('UTC')->startOfMinute(),
            'path' => 'api/v0/'.fake()->slug(2),
            'method' => 'GET',
            'status_code' => 404,
            'request_count' => fake()->numberBetween(1, 500),
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
}
