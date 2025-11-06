<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Addon;
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
        $addons = Addon::where('comments_disabled', false)->get();
        $allUsers = User::all();

        // Add comments to mods
        $this->seedCommentsForMods($mods, $allUsers);

        // Add comments to addons
        $this->seedCommentsForAddons($addons, $allUsers);

        // Add reactions to all comments
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
     * Seed comments for addons.
     *
     * @param  Collection<int, Addon>  $addons
     * @param  Collection<int, User>  $allUsers
     */
    private function seedCommentsForAddons(Collection $addons, Collection $allUsers): void
    {
        if ($addons->isEmpty()) {
            return;
        }

        Comment::withoutEvents(function () use ($addons, $allUsers) {
            progress(
                label: 'Adding Addon Comments...',
                steps: $addons,
                callback: function (Addon $addon, Progress $progress) use ($allUsers) {
                    $this->seedAddonComments($addon, $allUsers);
                }
            );
        });
    }

    /**
     * Seed comments for a single addon.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedAddonComments(Addon $addon, Collection $allUsers): void
    {
        // Create 1-15 parent comments (fewer than mods typically)
        $parentCommentCount = rand(1, 15);

        for ($i = 0; $i < $parentCommentCount; $i++) {
            $comment = $this->createComment($addon, $allUsers);

            // For each comment, 30% chance to have replies
            if (rand(0, 9) < 3) {
                $this->createReplies($comment, $allUsers);
            }
        }
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
     * @param  Mod|Addon  $commentable
     * @param  Collection<int, User>  $allUsers
     */
    private function createComment($commentable, Collection $allUsers): Comment
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
            ->recycle([$commentable])
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
        // Add 1-5 reactions from different users (but no more than available users)
        $maxReactions = min(5, $allUsers->count());
        $reactionCount = rand(1, $maxReactions);

        // Use shuffle and take to ensure unique users
        $reactingUsers = $allUsers->shuffle()->take($reactionCount);

        foreach ($reactingUsers as $user) {
            // Check if this user has already reacted to this comment
            $existingReaction = CommentReaction::where('user_id', $user->id)
                ->where('comment_id', $comment->id)
                ->exists();

            if (! $existingReaction) {
                CommentReaction::factory()
                    ->recycle([$comment])
                    ->recycle([$user])
                    ->create();
            }
        }
    }
}
