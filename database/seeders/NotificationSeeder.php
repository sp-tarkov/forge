<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user or a specific user to receive notifications
        $user = User::query()->whereEmail('test@example.com')->first();

        if (! $user) {
            $this->command->error('Test user not found. Please run DatabaseSeeder first.');

            return;
        }

        // Get some mods to comment on
        $mods = Mod::inRandomOrder()->limit(10)->get();

        if ($mods->isEmpty()) {
            $this->command->error('No mods found. Please run DatabaseSeeder first.');

            return;
        }

        // Get some users who will be commenters
        $commenters = User::where('id', '!=', $user->id)->inRandomOrder()->limit(10)->get();

        if ($commenters->isEmpty()) {
            $this->command->error('Not enough users found to create comments. Please run DatabaseSeeder first.');

            return;
        }

        $this->command->info("Creating notifications for user: {$user->name}");

        // Create 10-20 notifications
        $notificationCount = fake()->numberBetween(10, 20);

        for ($i = 0; $i < $notificationCount; $i++) {
            // 80% mod comments, 20% profile comments
            $isModComment = fake()->boolean(80);

            if ($isModComment && $mods->isNotEmpty()) {
                $commentable = $mods->random();
            } else {
                // Comment on a random user's profile
                $profileUser = User::inRandomOrder()->first();
                $commentable = $profileUser;
            }

            // Create a comment
            $commenter = $commenters->random();
            $createdAt = fake()->dateTimeBetween('-30 days', 'now');

            $comment = Comment::create([
                'user_id' => $commenter->id,
                'commentable_type' => get_class($commentable),
                'commentable_id' => $commentable->id,
                'body' => fake()->paragraph(fake()->numberBetween(1, 3)),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            /** @var Commentable<Model> $commentable */

            // Create notification data
            $notificationData = [
                'comment_id' => $comment->id,
                'comment_body' => $comment->body,
                'commenter_name' => $commenter->name,
                'commenter_id' => $commenter->id,
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'commentable_title' => $commentable->getTitle(),
                'comment_url' => $this->getCommentUrl($comment),
            ];

            // Insert notification directly into database
            DB::table('notifications')->insert([
                'id' => fake()->uuid(),
                'type' => NewCommentNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($notificationData),
                'read_at' => fake()->boolean(30) ? fake()->dateTimeBetween($createdAt, 'now') : null, // 30% read
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->command->info("Created {$notificationCount} notifications for {$user->email}!");
    }

    /**
     * Get the URL to view the comment.
     */
    private function getCommentUrl(Comment $comment): string
    {
        return $comment->getUrl();
    }
}
