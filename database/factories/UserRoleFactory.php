<?php

namespace Database\Factories;

use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UserRoleFactory extends Factory
{
    protected $model = UserRole::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'short_name' => $this->faker->word(),
            'description' => $this->faker->text(),
            'color_class' => $this->faker->randomElement(['sky', 'red', 'green', 'emerald', 'lime']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Define the "administrator" role.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Administrator',
            'short_name' => 'Admin',
            'description' => 'An administrator has full access to the site.',
            'color_class' => 'sky',
        ]);
    }

    /**
     * Define the "moderator" role.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Moderator',
            'short_name' => 'Mod',
            'description' => 'A moderator has the ability to moderate user content.',
            'color_class' => 'emerald',
        ]);
    }
}
