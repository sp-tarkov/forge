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
            'version_constraint' => '^'.$this->faker->numerify('#.#.#'),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }
}