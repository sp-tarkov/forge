<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'link' => $this->faker->url,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
