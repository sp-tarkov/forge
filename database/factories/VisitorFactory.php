<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Visitor>
 */
class VisitorFactory extends Factory
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
            'type' => 'visitor',
            'session_id' => Str::random(40),
            'user_id' => $this->faker->boolean(30) ? User::factory() : null,
            'last_activity' => Carbon::now()->subSeconds($this->faker->numberBetween(0, 300)),
        ];
    }

    /**
     * Indicate that the visitor is authenticated.
     */
    public function authenticated(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the visitor is a guest.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the visitor is active (within last 120 seconds).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity' => Carbon::now()->subSeconds($this->faker->numberBetween(0, 120)),
        ]);
    }

    /**
     * Indicate that the visitor is inactive (older than 120 seconds).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity' => Carbon::now()->subSeconds($this->faker->numberBetween(121, 600)),
        ]);
    }

    /**
     * Create a peak record.
     */
    public function peak(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'peak',
            'session_id' => 'PEAK_RECORD',
            'user_id' => null,
            'last_activity' => null,
            'peak_count' => $this->faker->numberBetween(10, 500),
            'peak_date' => Carbon::now()->subDays($this->faker->numberBetween(0, 30)),
        ]);
    }
}
