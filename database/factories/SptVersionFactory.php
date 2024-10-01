<?php

namespace Database\Factories;

use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SptVersion>
 */
class SptVersionFactory extends Factory
{
    protected $model = SptVersion::class;

    public function definition(): array
    {
        return [
            'version' => $this->faker->numerify('#.#.#'),
            'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
            'link' => $this->faker->url,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
