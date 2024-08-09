<?php

namespace Database\Factories;

use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SptVersionFactory extends Factory
{
    protected $model = SptVersion::class;

    public function definition(): array
    {
        return [
            'version' => $this->faker->numerify('SPT 1.#.#'),
            'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
