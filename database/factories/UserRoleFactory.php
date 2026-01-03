<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<UserRole>
 */
class UserRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'short_name' => $this->faker->word(),
            'description' => $this->faker->text(),
            'color_class' => $this->faker->randomElement(['sky', 'red', 'green', 'emerald', 'lime']),
            'icon' => $this->faker->randomElement(['shield-check', 'star', 'wrench', 'flag']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Define the "staff" role.
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Staff',
            'short_name' => 'Staff',
            'description' => 'A staff member has full access to the site.',
            'color_class' => 'red',
            'icon' => 'shield-check',
        ]);
    }

    /**
     * Define the "senior moderator" role.
     */
    public function seniorModerator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Senior Moderator',
            'short_name' => 'Sr. Mod',
            'description' => 'A senior moderator can moderate content and ban users.',
            'color_class' => 'orange',
            'icon' => 'shield-exclamation',
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
            'description' => 'A moderator can moderate user content.',
            'color_class' => 'orange',
            'icon' => 'wrench',
        ]);
    }
}
