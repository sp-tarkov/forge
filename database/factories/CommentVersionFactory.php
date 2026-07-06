<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<CommentVersion>
 */
final class CommentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'body' => fake()->paragraphs(random_int(1, 3), true),
            'version_number' => 1,
            'created_at' => Date::now(),
        ];
    }

    /**
     * Create a version with a specific version number.
     */
    public function version(int $versionNumber): static
    {
        return $this->state(fn (array $attributes): array => [
            'version_number' => $versionNumber,
        ]);
    }

    /**
     * Create a version whose language has been detected without needing a translation.
     */
    public function languageDetected(?string $language = 'en'): static
    {
        return $this->state(fn (array $attributes): array => [
            'detected_language' => $language,
            'language_detected_at' => Date::now(),
        ]);
    }

    /**
     * Create a version translated into English from another language.
     */
    public function translated(string $language = 'ru'): static
    {
        return $this->state(fn (array $attributes): array => [
            'detected_language' => $language,
            'translated_body' => fake()->paragraph(),
            'translation_metadata' => ['provider' => 'anthropic'],
            'language_detected_at' => Date::now(),
            'translated_at' => Date::now(),
        ]);
    }
}
