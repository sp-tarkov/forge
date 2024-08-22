<?php

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ModVersionFactory extends Factory
{
    protected $model = ModVersion::class;

    public function definition(): array
    {
        $constraint = fake()->numerify($this->generateVersionConstraint());

        return [
            'mod_id' => Mod::factory(),
            'version' => fake()->numerify('#.#.#'),
            'description' => fake()->text(),
            'link' => fake()->url(),
            'spt_version_constraint' => $constraint,
            'resolved_spt_version_id' => null,
            'virus_total_link' => fake()->url(),
            'downloads' => fake()->randomNumber(),
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * This method generates a random version constraint from a predefined set of options.
     */
    private function generateVersionConstraint(): string
    {
        $versionConstraints = ['*', '^1.#.#', '>=2.#.#', '~1.#.#'];

        return $versionConstraints[array_rand($versionConstraints)];
    }

    /**
     * Indicate that the mod version should have a resolved SPT version.
     */
    public function sptVersionResolved(): static
    {
        $constraint = fake()->numerify('#.#.#');

        return $this->state(fn (array $attributes) => [
            'spt_version_constraint' => $constraint,
            'resolved_spt_version_id' => SptVersion::factory()->create([
                'version' => $constraint,
            ]),
        ]);
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
