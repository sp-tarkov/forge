<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Mod;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

class CommentSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();

        $mods = Mod::all();
        $allUsers = User::all();

        // Add comments to mods
        $this->seedCommentsForMods($mods, $allUsers);

        // Add reactions to comments
        $this->seedCommentReactions($allUsers);
    }

    /**
     * Seed comments for mods.
     *
     * @param  Collection<int, Mod>  $mods
     * @param  Collection<int, User>  $allUsers
     */
    private function seedCommentsForMods(Collection $mods, Collection $allUsers): void
    {
        Comment::withoutEvents(function () use ($mods, $allUsers) {
            progress(
                label: 'Adding Comments...',
                steps: $mods,
                callback: function (Mod $mod, Progress $progress) use ($allUsers) {
                    $this->seedModComments($mod, $allUsers);
                }
            );
        });
    }

    /**
     * Seed comments for a single mod.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedModComments(Mod $mod, Collection $allUsers): void
    {
        // Create 1-20 parent comments with varied spam statuses
        $parentCommentCount = rand(1, 20);

        for ($i = 0; $i < $parentCommentCount; $i++) {
            $comment = $this->createComment($mod, $allUsers);

            // For each comment, 30% chance to have replies
            if (rand(0, 9) < 3) {
                $this->createReplies($comment, $allUsers);
            }
        }
    }

    /**
     * Create a single comment.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function createComment(Mod $mod, Collection $allUsers): Comment
    {
        $spamStatus = $this->getRandomSpamStatus();
        $isDeleted = rand(0, 100) < 10; // 10% chance to be deleted

        $commentData = [
            'spam_status' => $spamStatus,
        ];

        if ($isDeleted) {
            $commentData['deleted_at'] = now()->subDays(rand(1, 30));
        }

        return Comment::factory()
            ->recycle([$mod])
            ->recycle($allUsers)
            ->create($commentData);
    }

    /**
     * Create replies to a comment.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function createReplies(Comment $parentComment, Collection $allUsers): void
    {
        // Create 1-4 replies to the parent comment
        $replyCount = rand(1, 4);

        for ($j = 0; $j < $replyCount; $j++) {
            $firstLevelReply = $this->createReply($parentComment, $allUsers, 8);

            // For each first-level reply, 40% chance to have nested replies
            if (rand(0, 9) < 4) {
                $this->createNestedReplies($firstLevelReply, $allUsers);
            }
        }
    }

    /**
     * Create a reply to a comment.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function createReply(Comment $parentComment, Collection $allUsers, int $deletionChance): Comment
    {
        $spamStatus = $this->getRandomSpamStatus();
        $isDeleted = rand(0, 100) < $deletionChance;

        $replyData = [
            'spam_status' => $spamStatus,
        ];

        if ($isDeleted) {
            $replyData['deleted_at'] = now()->subDays(rand(1, 15));
        }

        return Comment::factory()
            ->reply($parentComment)
            ->recycle($allUsers)
            ->create($replyData);
    }

    /**
     * Create nested replies.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function createNestedReplies(Comment $firstLevelReply, Collection $allUsers): void
    {
        // Create 1-2 nested replies
        $nestedReplyCount = rand(1, 2);

        for ($k = 0; $k < $nestedReplyCount; $k++) {
            $this->createReply($firstLevelReply, $allUsers, 5);
        }
    }

    /**
     * Seed reactions for comments.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedCommentReactions(Collection $allUsers): void
    {
        CommentReaction::withoutEvents(function () use ($allUsers) {
            progress(
                label: 'Adding Comment Reactions...',
                steps: Comment::all(),
                callback: function (Comment $comment, Progress $progress) use ($allUsers) {
                    // 40% chance to have reactions
                    if (rand(0, 9) < 4) {
                        $this->addReactionsToComment($comment, $allUsers);
                    }
                }
            );
        });
    }

    /**
     * Add reactions to a comment.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function addReactionsToComment(Comment $comment, Collection $allUsers): void
    {
        // Add 1-5 reactions from different users
        $reactingUsers = $allUsers->random(rand(1, 5));

        foreach ($reactingUsers as $user) {
            CommentReaction::factory()
                ->recycle([$comment])
                ->recycle([$user])
                ->create();
        }
    }
}
