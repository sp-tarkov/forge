<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SourceCodeLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mod>
 */
final class ModFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(mb_rtrim(fake()->sentence(random_int(3, 5)), '.'));
        $domain = fake()->domainName();
        $modSlug = Str::slug($name);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => $modSlug,
            'guid' => 'com.'.explode('.', $domain)[0].'.'.$modSlug,
            'teaser' => fake()->sentence(),
            'description' => fake()->paragraphs(random_int(4, 20), true),
            'license_id' => License::query()->inRandomOrder()->first()->id ?? License::factory(),
            'category_id' => ModCategory::query()->inRandomOrder()->first()->id ?? ModCategory::factory(),
            'featured' => fake()->boolean(),
            'contains_ai_content' => fake()->boolean(),
            'contains_ads' => fake()->boolean(),
            'discord_notification_sent' => true,
            'published_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
            'created_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
            'updated_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
        ];
    }

    /**
     * Indicate that the mod should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disabled' => true,
        ]);
    }

    /**
     * Indicate that the mod should be unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the mod has addons enabled.
     */
    public function addonsEnabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'addons_disabled' => false,
        ]);
    }

    /**
     * Indicate that the mod has addons disabled.
     */
    public function addonsDisabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'addons_disabled' => true,
        ]);
    }

    /**
     * Indicate that the mod has profile binding notice disabled.
     */
    public function profileBindingNoticeDisabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'profile_binding_notice_disabled' => true,
        ]);
    }

    /**
     * Indicate that the mod should show the cheat notice.
     */
    public function withCheatNotice(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cheat_notice' => true,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Mod $mod): void {
            // Create 1-3 source code links for each mod
            $numberOfLinks = random_int(1, 3);
            for ($i = 0; $i < $numberOfLinks; $i++) {
                SourceCodeLink::factory()->create([
                    'sourceable_type' => Mod::class,
                    'sourceable_id' => $mod->id,
                ]);
            }
        });
    }
}
