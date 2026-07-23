<?php

declare(strict_types=1);

use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Notification::fake();
    Queue::fake();
});

describe('reply notifications', function (): void {
    it('notifies the parent comment author of a reply', function (): void {
        $parentAuthor = User::factory()->create();
        $replier = User::factory()->create();
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $parentAuthor->id,
        ]);
        $reply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
        ]);

        new ProcessCommentNotification($reply)->handle();

        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class);
    });

    it('does not notify a parent author who has blocked the replier', function (): void {
        $parentAuthor = User::factory()->create();
        $replier = User::factory()->create();
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $parentAuthor->id,
        ]);
        $reply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
        ]);

        $parentAuthor->block($replier);

        new ProcessCommentNotification($reply)->handle();

        Notification::assertNotSentTo($parentAuthor, CommentReplyNotification::class);
    });

    it('does not notify a parent author who is blocked by the replier', function (): void {
        $parentAuthor = User::factory()->create();
        $replier = User::factory()->create();
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $parentAuthor->id,
        ]);
        $reply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
        ]);

        $replier->block($parentAuthor);

        new ProcessCommentNotification($reply)->handle();

        Notification::assertNotSentTo($parentAuthor, CommentReplyNotification::class);
    });
});

describe('subscriber notifications', function (): void {
    it('notifies subscribers of a new comment', function (): void {
        $subscriber = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->subscribeUser($subscriber);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $commenter->id,
        ]);

        new ProcessCommentNotification($comment)->handle();

        Notification::assertSentTo($subscriber, NewCommentNotification::class);
    });

    it('does not notify subscribers with a block relationship with the comment author', function (): void {
        $blockingSubscriber = User::factory()->create();
        $blockedSubscriber = User::factory()->create();
        $normalSubscriber = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->subscribeUser($blockingSubscriber);
        $mod->subscribeUser($blockedSubscriber);
        $mod->subscribeUser($normalSubscriber);

        $blockingSubscriber->block($commenter);
        $commenter->block($blockedSubscriber);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $commenter->id,
        ]);

        new ProcessCommentNotification($comment)->handle();

        Notification::assertNotSentTo($blockingSubscriber, NewCommentNotification::class);
        Notification::assertNotSentTo($blockedSubscriber, NewCommentNotification::class);
        Notification::assertSentTo($normalSubscriber, NewCommentNotification::class);
    });
});
