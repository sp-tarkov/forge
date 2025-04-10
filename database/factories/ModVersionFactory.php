<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
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
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): ModVersionFactory
    {
        return $this->afterCreating(function (ModVersion $modVersion) {
            $this->ensureSptVersionsExist($modVersion); // Create SPT Versions
        });
    }

    /**
     * Ensure that the required SPT versions exist and are associated with the mod version.
     */
    protected function ensureSptVersionsExist(ModVersion $modVersion): void
    {
        $constraint = $modVersion->spt_version_constraint;

        $requiredVersions = match ($constraint) {
            '^1.0' => ['1.0.0', '1.1.0', '1.2.0'],
            '^2.0' => ['2.0.0', '2.1.0'],
            '>=3.0' => ['3.0.0', '3.1.0', '3.2.0', '4.0.0'],
            '<4.0' => ['1.0.0', '2.0.0', '3.0.0'],
            default => [],
        };

        // If the version is anything but the default, no SPT versions are created.
        if (! $requiredVersions) {
            return;
        }

        foreach ($requiredVersions as $version) {
            SptVersion::firstOrCreate(['version' => $version], [
                'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
                'link' => $this->faker->url(),
            ]);
        }

        $modVersion->sptVersions()->sync(SptVersion::whereIn('version', $requiredVersions)->pluck('id')->toArray());
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
