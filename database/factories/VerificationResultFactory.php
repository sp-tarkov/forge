<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationResult>
 */
final class VerificationResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'verifiable_type' => $this->faker->randomElement([ModVersion::class, AddonVersion::class]),
            'verifiable_id' => function (array $attributes) {
                /** @var class-string<ModVersion>|class-string<AddonVersion> $modelClass */
                $modelClass = $attributes['verifiable_type'];

                return $modelClass::factory();
            },
            'status' => VerificationStatus::Pending,
            'trigger' => VerificationTrigger::Manual,
            'download_url' => $this->faker->url().'/'.$this->faker->slug().'.zip',
        ];
    }

    /**
     * Configure the factory for a ModVersion.
     */
    public function forModVersion(ModVersion $modVersion): static
    {
        return $this->state([
            'verifiable_type' => ModVersion::class,
            'verifiable_id' => $modVersion->id,
            'download_url' => $modVersion->link,
        ]);
    }

    /**
     * Configure the factory for an AddonVersion.
     */
    public function forAddonVersion(AddonVersion $addonVersion): static
    {
        return $this->state([
            'verifiable_type' => AddonVersion::class,
            'verifiable_id' => $addonVersion->id,
            'download_url' => $addonVersion->link,
        ]);
    }

    /**
     * Set the verification as passed.
     */
    public function passed(): static
    {
        return $this->state([
            'status' => VerificationStatus::Passed,
            'download_ok' => true,
            'downloaded_size' => $this->faker->numberBetween(1024, 50 * 1024 * 1024),
            'downloaded_sha256' => $this->faker->sha256(),
            'archive_ok' => true,
            'file_tree' => ['package.json', 'src/mod.ts', 'README.md'],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);
    }

    /**
     * Set the verification as failed.
     */
    public function failed(string $reason = 'Download returned HTTP 404'): static
    {
        return $this->state([
            'status' => VerificationStatus::Failed,
            'download_ok' => false,
            'failure_reason' => $reason,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);
    }

    /**
     * Set the verification as errored.
     */
    public function errored(string $reason = 'Unexpected error during verification'): static
    {
        return $this->state([
            'status' => VerificationStatus::Error,
            'failure_reason' => $reason,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);
    }
}
