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
        return [
            'mod_id' => Mod::factory(),
            'version' => fake()->numerify('1.#.#'),
            'description' => fake()->text(),
            'link' => fake()->url(),
            'spt_version_id' => SptVersion::factory(),
            'virus_total_link' => fake()->url(),
            'downloads' => fake()->randomNumber(),
            'disabled' => fake()->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
