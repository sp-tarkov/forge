<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModCategory>
 */
class ModCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(rand(2, 4), true),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the category is from the Hub.
     */
    public function fromHub(int $hubId): static
    {
        return $this->state(fn (array $attributes) => [
            'hub_id' => $hubId,
        ]);
    }
}
