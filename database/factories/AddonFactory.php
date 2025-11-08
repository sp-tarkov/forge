<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\SourceCodeLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(mb_rtrim(fake()->sentence(rand(2, 4)), '.'));
        $addonSlug = Str::slug($name);

        return [
            'mod_id' => Mod::factory(),
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => $addonSlug,
            'teaser' => fake()->sentence(),
            'description' => fake()->paragraphs(rand(2, 5), true),
            'license_id' => License::inRandomOrder()->first()->id ?? License::factory(),
            'downloads' => 0,
            'contains_ai_content' => fake()->boolean(),
            'contains_ads' => fake()->boolean(),
            'comments_disabled' => false,
            'discord_notification_sent' => true,
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Indicate that the addon should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }

    /**
     * Indicate that the addon should be published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ]);
    }

    /**
     * Indicate that the addon should be unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the addon should be detached from its parent mod.
     */
    public function detached(): static
    {
        return $this->state(fn (array $attributes) => [
            'detached_at' => Carbon::now()->subDays(rand(0, 30)),
            'detached_by_user_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the addon should have versions.
     */
    public function withVersions(int $count = 1): static
    {
        return $this->has(
            \App\Models\AddonVersion::factory()->count($count),
            'versions'
        );
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Addon $addon): void {
            // Create 0-2 source code links for each addon
            $numberOfLinks = rand(0, 2);
            for ($i = 0; $i < $numberOfLinks; $i++) {
                SourceCodeLink::factory()->create([
                    'sourceable_type' => Addon::class,
                    'sourceable_id' => $addon->id,
                ]);
            }
        });
    }
}
