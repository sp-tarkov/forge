<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Exceptions\InvalidVersionNumberException;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Support\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AddonVersion>
 */
class AddonVersionFactory extends Factory
{
    public function definition(): array
    {
        $versionString = $this->faker->numerify('#.#.#');

        // Parse the version components directly in the factory to ensure that version segments are always set
        try {
            $version = new Version($versionString);
            $versionMajor = $version->getMajor();
            $versionMinor = $version->getMinor();
            $versionPatch = $version->getPatch();
            $versionLabels = $version->getLabels();
        } catch (InvalidVersionNumberException) {
            $versionMajor = 0;
            $versionMinor = 0;
            $versionPatch = 0;
            $versionLabels = '';
        }

        return [
            'addon_id' => Addon::factory(),
            'version' => $versionString,
            'version_major' => $versionMajor,
            'version_minor' => $versionMinor,
            'version_patch' => $versionPatch,
            'version_pre_release' => $versionLabels,
            'description' => fake()->text(),
            'link' => fake()->url(),
            'mod_version_constraint' => $this->faker->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),
            'content_length' => fake()->numberBetween(1000, 10000000),
            'downloads' => fake()->randomNumber(),
            'disabled' => false,
            'discord_notification_sent' => true,
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->has(
            \App\Models\VirusTotalLink::factory()->count(1),
            'virusTotalLinks'
        );
    }

    /**
     * Indicate that the addon version should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }

    /**
     * Indicate that the addon version should not have any VirusTotal links.
     */
    public function withoutVirusTotalLinks(): static
    {
        return $this->afterMaking(function (AddonVersion $addonVersion) {
            // Remove any auto-created VirusTotal links
        })->afterCreating(function (AddonVersion $addonVersion) {
            $addonVersion->virusTotalLinks()->delete();
        });
    }
}
