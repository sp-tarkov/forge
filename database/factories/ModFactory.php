<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ModFactory extends Factory
{
    protected $model = Mod::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'teaser' => $this->faker->sentence,
            'description' => $this->faker->sentences(6, true),
            'license_id' => License::factory(),
            'source_code_link' => $this->faker->url(),
            'featured' => $this->faker->boolean,
            'contains_ai_content' => $this->faker->boolean,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the mod should be soft-deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
