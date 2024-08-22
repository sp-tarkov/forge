<?php

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ModDependencyFactory extends Factory
{
    protected $model = ModDependency::class;

    public function definition(): array
    {
        return [
            'mod_version_id' => ModVersion::factory(),
            'dependency_mod_id' => Mod::factory(),
            'version_constraint' => fake()->numerify($this->generateVersionConstraint()),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * This method generates a random version constraint from a predefined set of options.
     */
    private function generateVersionConstraint(): string
    {
        $versionConstraints = ['*', '^1.#.#', '>=2.#.#', '~1.#.#', '>=1.2.# <2.#.#'];

        return $versionConstraints[array_rand($versionConstraints)];
    }
}
