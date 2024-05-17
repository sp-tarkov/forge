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
            'version' => $this->faker->numerify('1.#.#'),
            'color_class' => $this->faker->randomElement(['green', 'yellow', 'red', 'gray']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
