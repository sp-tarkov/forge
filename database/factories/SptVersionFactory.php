<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SptVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<SptVersion>
 */
final class SptVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => $this->faker->unique()->numerify('#.#.#'),
            'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
            'link' => $this->faker->url(),
            'publish_date' => Date::now()->subDays(7), // Default to published (a week ago)
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }

    /**
     * Indicate that the SPT version is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'publish_date' => null,
        ]);
    }

    /**
     * Indicate that the SPT version is scheduled for future publication.
     */
    public function scheduled(?CarbonInterface $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'publish_date' => $date ?? Date::now()->addWeek(),
        ]);
    }

    /**
     * Indicate that the SPT version was published at a specific date.
     */
    public function publishedAt(CarbonInterface $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'publish_date' => $date,
        ]);
    }
}
