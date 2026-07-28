<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Visitor>
 */
final class VisitorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Visitor>
     */
    protected $model = Visitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'peak_count' => $this->faker->numberBetween(10, 500),
            'peak_date' => Date::now()->subDays($this->faker->numberBetween(0, 30)),
        ];
    }
}
