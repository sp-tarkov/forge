<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ModVersion>
 */
class ModVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mod_id' => Mod::factory(),
            'version' => $this->faker->numerify('#.#.#'),
            'description' => fake()->text(),
            'link' => fake()->url(),

            // Unless a custom constraint is provided, this will also generate the required SPT versions.
            'spt_version_constraint' => $this->faker->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),

            'virus_total_link' => fake()->url(),
            'downloads' => fake()->randomNumber(),
            'disabled' => false,
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Indicate that the mod version should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
