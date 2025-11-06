<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FikaCompatibilityStatus;
use App\Exceptions\InvalidVersionNumberException;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Support\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ModVersion>
 */
class ModVersionFactory extends Factory
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
            'mod_id' => Mod::factory(),
            'version' => $versionString,
            'version_major' => $versionMajor,
            'version_minor' => $versionMinor,
            'version_patch' => $versionPatch,
            'version_labels' => $versionLabels,
            'description' => fake()->text(),
            'link' => fake()->url(),
            'spt_version_constraint' => $this->faker->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),
            'virus_total_link' => fake()->url(),
            'downloads' => fake()->randomNumber(),
            'disabled' => false,
            'fika_compatibility_status' => $this->faker->randomElement(FikaCompatibilityStatus::cases()),
            'discord_notification_sent' => true,
            'published_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Indicate that the mod version should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
