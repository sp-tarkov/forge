<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VirusTotalLink>
 */
class VirusTotalLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = $this->faker->sha256();

        return [
            'url' => "https://www.virustotal.com/gui/file/{$hash}",
            'label' => $this->faker->optional(0.3, '')->randomElement(['Main File', 'Alternative Download', 'Mirror']),
            'linkable_type' => $this->faker->randomElement([ModVersion::class, AddonVersion::class]),
            'linkable_id' => function (array $attributes) {
                return $attributes['linkable_type']::factory();
            },
        ];
    }

    /**
     * Configure the factory for a ModVersion.
     */
    public function forModVersion(ModVersion $modVersion): static
    {
        return $this->state([
            'linkable_type' => ModVersion::class,
            'linkable_id' => $modVersion->id,
        ]);
    }

    /**
     * Configure the factory for an AddonVersion.
     */
    public function forAddonVersion(AddonVersion $addonVersion): static
    {
        return $this->state([
            'linkable_type' => AddonVersion::class,
            'linkable_id' => $addonVersion->id,
        ]);
    }
}
