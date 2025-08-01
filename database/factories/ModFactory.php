<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mod>
 */
class ModFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->sentence(rand(3, 5));
        $domain = fake()->domainName();
        $modSlug = Str::slug($name);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => $modSlug,
            'guid' => 'com.'.explode('.', $domain)[0].'.'.$modSlug,
            'teaser' => fake()->sentence(),
            'description' => fake()->paragraphs(rand(4, 20), true),
            'license_id' => License::factory(),
            'source_code_url' => fake()->url(),
            'featured' => fake()->boolean(),
            'contains_ai_content' => fake()->boolean(),
            'contains_ads' => fake()->boolean(),
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Indicate that the mod should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
