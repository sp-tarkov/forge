<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PeakVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeakVisitor>
 */
final class PeakVisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'count' => fake()->numberBetween(1, 10000),
        ];
    }
}
