<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CommentReaction>
 */
class CommentReactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'comment_id' => Comment::factory(),
            'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
        ];
    }
}
