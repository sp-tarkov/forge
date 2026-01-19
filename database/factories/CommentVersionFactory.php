<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CommentVersion>
 */
class CommentVersionFactory extends Factory
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
            'body' => fake()->paragraphs(rand(1, 3), true),
            'version_number' => 1,
            'created_at' => Carbon::now(),
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
}
