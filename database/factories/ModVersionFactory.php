<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FikaCompatibility;
use App\Exceptions\InvalidVersionNumberException;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\VirusTotalLink;
use App\Support\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<ModVersion>
 */
final class ModVersionFactory extends Factory
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
            'link' => 'https://example.com/'.fake()->slug().'.7z',
            'spt_version_constraint' => $this->faker->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),
            'downloads' => fake()->randomNumber(),
            'disabled' => false,
            'fika_compatibility' => $this->faker->randomElement(FikaCompatibility::cases()),
            'discord_notification_sent' => true,
            'published_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
            'created_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
            'updated_at' => Date::now()->subDays(random_int(0, 365))->subHours(random_int(0, 23)),
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->has(
            VirusTotalLink::factory()->count(1),
            'virusTotalLinks'
        );
    }

    /**
     * Indicate that the mod version should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disabled' => true,
        ]);
    }

    /**
     * Indicate that the mod version should not have any VirusTotal links.
     */
    public function withoutVirusTotalLinks(): static
    {
        return $this->afterMaking(function (ModVersion $modVersion): void {
            // Remove any auto-created VirusTotal links
        })->afterCreating(function (ModVersion $modVersion): void {
            $modVersion->virusTotalLinks()->delete();
        });
    }
}
