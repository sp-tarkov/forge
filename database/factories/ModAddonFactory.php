<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\License;
use App\Models\ModAddon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModAddon>
 */
class ModAddonFactory extends Factory
{
    protected $model = ModAddon::class;

    public function definition(): array
    {
        $name = fake()->sentence(rand(3, 5));
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'owner_id' => User::factory(),
            'teaser' => fake()->sentence(),
            'description' => fake()->paragraphs(rand(4, 20), true),
            'thumbnail' => fake()->word(),
            'license_id' => License::factory(),
            'downloads' => fake()->randomNumber(),
            'source_code_url' => fake()->url(),
            'disabled' => fake()->boolean(),
            'featured' => fake()->boolean(),
            'contains_ai_content' => fake()->boolean(),
            'contains_ads' => fake()->boolean(),
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'mod_addon_id' => fake()->randomNumber(),
            'version' => fake()->word(),
            'version_major' => fake()->randomNumber(),
            'version_minor' => fake()->randomNumber(),
            'version_patch' => fake()->randomNumber(),
            'version_pre_release' => fake()->word(),
            'link' => fake()->word(),
            'spt_version_constraint' => fake()->word(),
            'virus_total_link' => fake()->word(),
        ];
    }

    /**
     * Indicate that the mod addon should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
