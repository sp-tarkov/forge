<?php

declare(strict_types=1);

use App\Jobs\CheckCommentForSpam;
use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\Mod;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

describe('Comment Notifications', function (): void {
    it('triggers spam check job on comment creation', function (): void {
        Bus::fake();

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        Bus::assertDispatched(CheckCommentForSpam::class, fn ($job) => $job->comment->is($comment));
    });

    it('automatically subscribes mod owner to comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Create a comment from another user
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the owner is subscribed
        expect(CommentSubscription::isSubscribed($owner, $mod))->toBeTrue();
    });

    it('automatically subscribes user to their profile comments', function (): void {
        $user = User::factory()->create();

        // Create a comment from another user on the profile
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $user->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the user is subscribed to their own profile
        expect(CommentSubscription::isSubscribed($user, $user))->toBeTrue();
    });

    it('sends notification to subscribers after five minutes', function (): void {
        Notification::fake();

        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Manually process the notification (simulating after 5 minutes)
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Assert notification was sent to the owner
        Notification::assertSentTo($owner, NewCommentNotification::class);

        // Assert notification was NOT sent to the commenter
        Notification::assertNotSentTo($commenter, NewCommentNotification::class);
    });

    it('does not send notification if comment is deleted', function (): void {
        // Fake both notifications and bus to control job execution
        Notification::fake();
        Bus::fake();

        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // First, ensure the owner is subscribed
        expect(CommentSubscription::isSubscribed($owner, $mod))->toBeTrue();

        // Soft delete the comment (set deleted_at)
        $comment->update(['deleted_at' => now()]);

        // Verify the comment is marked as deleted
        $freshComment = Comment::query()->find($comment->id);
        expect($freshComment->isDeleted())->toBeTrue();
        expect($freshComment->deleted_at)->not->toBeNull();

        // Try to process the notification manually
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Assert no notification was sent
        Notification::assertNothingSent();
    });

    it('allows users to subscribe and unsubscribe from commentable', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Initially not subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        // Subscribe
        CommentSubscription::subscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        // Unsubscribe
        CommentSubscription::unsubscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();
    });

    it('sends database notifications even when email notifications are disabled', function (): void {
        Notification::fake();

        $owner = User::factory()->create(['email_notifications_enabled' => false]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Process the notification
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Assert database notification was sent but not email
        Notification::assertSentTo($owner, NewCommentNotification::class, fn ($notification, $channels): bool => in_array('database', $channels) && ! in_array('mail', $channels));
    });
});
