<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ban;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ban>
 */
final class BanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment' => fake()->sentence(),
            'expired_at' => null,
        ];
    }
}
