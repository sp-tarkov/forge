<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<CommentReaction>
 */
final class CommentReactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'comment_id' => Comment::factory(),
            'created_at' => Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23)),
            'updated_at' => Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23)),
        ];
    }
}
