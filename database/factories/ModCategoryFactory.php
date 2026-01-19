<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $title = fake()->unique()->words(rand(2, 4), true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
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

    /**
     * Indicate that the category shows profile binding notice.
     */
    public function showsProfileBindingNotice(): static
    {
        return $this->state(fn (array $attributes) => [
            'shows_profile_binding_notice' => true,
        ]);
    }
}
