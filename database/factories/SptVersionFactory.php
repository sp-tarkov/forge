<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SptVersion>
 */
class SptVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => $this->faker->numerify('#.#.#'),
            'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
            'link' => $this->faker->url(),
            'publish_date' => Carbon::now()->subDays(7), // Default to published (a week ago)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Indicate that the SPT version is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'publish_date' => null,
        ]);
    }

    /**
     * Indicate that the SPT version is scheduled for future publication.
     */
    public function scheduled(?\Carbon\Carbon $date = null): static
    {
        return $this->state(fn (array $attributes) => [
            'publish_date' => $date ?? Carbon::now()->addWeek(),
        ]);
    }

    /**
     * Indicate that the SPT version was published at a specific date.
     */
    public function publishedAt(\Carbon\Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'publish_date' => $date,
        ]);
    }
}
