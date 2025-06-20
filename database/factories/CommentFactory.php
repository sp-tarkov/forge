<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => Mod::class,
            'commentable_id' => Mod::factory(),
            'parent_id' => null,
            'body' => fake()->paragraphs(rand(1, 3), true),
            'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Indicate that the comment is a reply to another comment.
     */
    public function reply(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
        ]);
    }
}
