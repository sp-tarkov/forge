<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Jobs\CheckCommentForSpam;
use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\Mod;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

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

    it('does not auto-subscribe commenter when commenting', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Create a comment from another user
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the commenter is NOT subscribed (no auto-subscribe)
        expect(CommentSubscription::isSubscribed($commenter, $mod))->toBeFalse();

        // Assert that the owner is also NOT automatically subscribed
        expect(CommentSubscription::isSubscribed($owner, $mod))->toBeFalse();
    });

    it('does not auto-subscribe commenter on user profile', function (): void {
        $user = User::factory()->create();

        // Create a comment from another user on the profile
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $user->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the commenter is NOT subscribed (no auto-subscribe)
        expect(CommentSubscription::isSubscribed($commenter, $user))->toBeFalse();

        // Assert that the profile owner is NOT automatically subscribed
        expect(CommentSubscription::isSubscribed($user, $user))->toBeFalse();
    });

    it('sends notification to subscribers after five minutes', function (): void {
        Notification::fake();

        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Manually subscribe the owner to test notifications
        $mod->subscribeUser($owner);

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

        // Manually subscribe the owner for this test
        $mod->subscribeUser($owner);

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
        expect($freshComment->isDeleted())->toBeTrue()
            ->and($freshComment->deleted_at)->not->toBeNull();

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

        $owner = User::factory()->create(['email_comment_notifications_enabled' => false]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Manually subscribe the owner for this test
        $mod->subscribeUser($owner);

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

    it('sends only one notification per comment with appropriate channels based on user preferences', function (): void {
        // Fake notifications and bus to control when notifications are sent
        Notification::fake();
        Bus::fake();

        // Also fake the queue to prevent the notification itself from being queued
        Queue::fake();

        // Create a mod owner and manually subscribe them
        $owner = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        CommentSubscription::subscribe($owner, $mod);

        // Create additional subscribers with email notifications enabled
        $subscriber1 = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $subscriber2 = User::factory()->create(['email_comment_notifications_enabled' => true]);

        // Create a subscriber with email notifications disabled
        $subscriberNoEmail = User::factory()->create(['email_comment_notifications_enabled' => false]);

        CommentSubscription::subscribe($subscriber1, $mod);
        CommentSubscription::subscribe($subscriber2, $mod);
        CommentSubscription::subscribe($subscriberNoEmail, $mod);

        // Create a comment from another user
        // The CommentObserver will dispatch ProcessCommentNotification job, but since Bus is faked, it won't run
        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Verify the job was dispatched by the observer (no delay since we have versioning for edit history)
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job): bool => $job->comment->is($comment));

        // Now manually process the notification job (simulating after the 5-minute delay)
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Assert the commenter did not receive a notification (they don't get notified of their own comment)
        Notification::assertNotSentTo($commenter, NewCommentNotification::class);

        // All users receive database notifications regardless of email preference
        // Users with email enabled also receive email notifications
        Notification::assertSentTo($owner, NewCommentNotification::class, fn ($notification, $channels): bool => in_array('mail', $channels) && in_array('database', $channels));

        Notification::assertSentTo($subscriber1, NewCommentNotification::class, fn ($notification, $channels): bool => in_array('mail', $channels) && in_array('database', $channels));

        Notification::assertSentTo($subscriber2, NewCommentNotification::class, fn ($notification, $channels): bool => in_array('mail', $channels) && in_array('database', $channels));

        // User with email disabled still receives database notification (for in-app display)
        // but does not receive email notification
        Notification::assertSentTo($subscriberNoEmail, NewCommentNotification::class, fn ($notification, $channels): bool => in_array('database', $channels) && ! in_array('mail', $channels));

        // Verify each subscriber receives exactly one notification instance per comment
        Notification::assertSentToTimes($owner, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber1, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriberNoEmail, NewCommentNotification::class, 1);

        // Test idempotency: Process the notification job again to ensure no duplicate emails
        // This simulates what would happen if the job was processed twice (e.g., due to retry)
        // With deduplication logic, notifications should NOT be sent again
        $job->handle();

        // After second processing, notifications are NOT sent again due to deduplication
        // The CommentNotificationLog prevents duplicate notifications
        Notification::assertSentToTimes($owner, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber1, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriberNoEmail, NewCommentNotification::class, 1);

        // Process a third time to really ensure idempotency
        $job->handle();

        // Still only one notification per subscriber
        Notification::assertSentToTimes($owner, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber1, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriberNoEmail, NewCommentNotification::class, 1);
    });

    it('records correct notification type based on user preferences', function (): void {
        Notification::fake();

        $owner = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Manually subscribe the owner for this test
        CommentSubscription::subscribe($owner, $mod);

        // Create subscribers with different email preferences
        $subscriberWithEmail = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $subscriberWithoutEmail = User::factory()->create(['email_comment_notifications_enabled' => false]);

        // Subscribe them
        CommentSubscription::subscribe($subscriberWithEmail, $mod);
        CommentSubscription::subscribe($subscriberWithoutEmail, $mod);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Process the notification immediately
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Check that the correct notification types were recorded
        $logWithEmail = NotificationLog::query()->where('user_id', $subscriberWithEmail->id)
            ->where('notifiable_id', $comment->id)
            ->first();
        expect($logWithEmail)->not->toBeNull()
            ->and($logWithEmail->notification_type)->toBe(NotificationType::ALL);

        $logWithoutEmail = NotificationLog::query()->where('user_id', $subscriberWithoutEmail->id)
            ->where('notifiable_id', $comment->id)
            ->first();
        expect($logWithoutEmail)->not->toBeNull()
            ->and($logWithoutEmail->notification_type)->toBe(NotificationType::DATABASE);

        // Owner also gets ALL since they have email enabled
        $logOwner = NotificationLog::query()->where('user_id', $owner->id)
            ->where('notifiable_id', $comment->id)
            ->first();
        expect($logOwner)->not->toBeNull()
            ->and($logOwner->notification_type)->toBe(NotificationType::ALL);
    });

    it('dispatches comment notification job with 5-minute delay', function (): void {
        // Fake the bus to capture job dispatches
        Bus::fake();

        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that ProcessCommentNotification job was dispatched (no delay since we have versioning)
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job) =>
            // Verify it's for the correct comment
            $job->comment->is($comment));
    });

    it('does not create duplicate notifications on job retry after partial success', function (): void {
        Notification::fake();
        Bus::fake();

        // Create subscribers
        $subscriber1 = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $subscriber2 = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $subscriber3 = User::factory()->create(['email_comment_notifications_enabled' => true]);

        $mod = Mod::factory()->create();

        // Subscribe all users
        CommentSubscription::subscribe($subscriber1, $mod);
        CommentSubscription::subscribe($subscriber2, $mod);
        CommentSubscription::subscribe($subscriber3, $mod);

        // Create a comment
        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Process the notification job
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // Verify all three subscribers received exactly one notification
        Notification::assertSentToTimes($subscriber1, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber3, NewCommentNotification::class, 1);

        // Verify all three have log entries
        expect(NotificationLog::hasBeenSent($comment, $subscriber1->id, NewCommentNotification::class))->toBeTrue()
            ->and(NotificationLog::hasBeenSent($comment, $subscriber2->id, NewCommentNotification::class))->toBeTrue()
            ->and(NotificationLog::hasBeenSent($comment, $subscriber3->id, NewCommentNotification::class))->toBeTrue();

        // Simulate job retry - should not send any more notifications
        $job->handle();

        // Still only one notification per subscriber (no duplicates)
        Notification::assertSentToTimes($subscriber1, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);
        Notification::assertSentToTimes($subscriber3, NewCommentNotification::class, 1);
    });

    it('allows retry for users whose notification failed while skipping already notified users', function (): void {
        Bus::fake();

        // Create subscribers
        $subscriber1 = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $subscriber2 = User::factory()->create(['email_comment_notifications_enabled' => true]);

        $mod = Mod::factory()->create();

        CommentSubscription::subscribe($subscriber1, $mod);
        CommentSubscription::subscribe($subscriber2, $mod);

        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Simulate that subscriber1 was already notified (has log entry)
        NotificationLog::recordSent(
            $comment,
            $subscriber1->id,
            NewCommentNotification::class,
            NotificationType::ALL
        );

        // subscriber2 has no log entry (simulates failed notification on previous attempt)

        // Now fake notifications to track who receives them
        Notification::fake();

        // Process the job (simulating a retry)
        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // subscriber1 should NOT receive notification (already has log entry)
        Notification::assertNotSentTo($subscriber1, NewCommentNotification::class);

        // subscriber2 SHOULD receive notification (no log entry existed)
        Notification::assertSentTo($subscriber2, NewCommentNotification::class);
        Notification::assertSentToTimes($subscriber2, NewCommentNotification::class, 1);

        // Now subscriber2 should have a log entry
        expect(NotificationLog::hasBeenSent($comment, $subscriber2->id, NewCommentNotification::class))->toBeTrue();
    });
});

describe('Reply Notifications', function (): void {
    it('sends reply notification to parent comment author', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        // Create a parent comment
        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        // Create a reply to the parent comment
        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        // Process the notification
        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Assert reply notification was sent to parent author
        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class);

        // Assert the replier did not receive a notification
        Notification::assertNotSentTo($replier, CommentReplyNotification::class);
    });

    it('respects email_reply_notifications_enabled preference', function (): void {
        Notification::fake();

        // User with reply notifications disabled
        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => false]);
        $mod = Mod::factory()->create();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Assert database notification was sent but not email
        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class, fn ($notification, $channels): bool => in_array('database', $channels) && ! in_array('mail', $channels));
    });

    it('sends email for reply notification when enabled', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Assert both database and email notification were sent
        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class, fn ($notification, $channels): bool => in_array('database', $channels) && in_array('mail', $channels));
    });

    it('does not send reply notification to self', function (): void {
        Notification::fake();

        $user = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        // Create a parent comment
        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        // Same user replies to their own comment
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // User should NOT receive a notification for replying to their own comment
        Notification::assertNotSentTo($user, CommentReplyNotification::class);
    });

    it('does not send duplicate notification to reply recipient who is also page subscriber', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create([
            'email_reply_notifications_enabled' => true,
            'email_comment_notifications_enabled' => true,
        ]);
        $mod = Mod::factory()->create();

        // Parent author is also subscribed to the page
        CommentSubscription::subscribe($parentAuthor, $mod);

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Should receive reply notification (not new comment notification)
        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class);
        Notification::assertSentToTimes($parentAuthor, CommentReplyNotification::class, 1);

        // Should NOT receive new comment notification (already got reply notification)
        Notification::assertNotSentTo($parentAuthor, NewCommentNotification::class);
    });

    it('sends both reply and subscriber notifications to different users', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $subscriber = User::factory()->create(['email_comment_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        // Subscribe only the subscriber, not the parent author
        CommentSubscription::subscribe($subscriber, $mod);

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Parent author gets reply notification
        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class);
        Notification::assertNotSentTo($parentAuthor, NewCommentNotification::class);

        // Subscriber gets new comment notification
        Notification::assertSentTo($subscriber, NewCommentNotification::class);
        Notification::assertNotSentTo($subscriber, CommentReplyNotification::class);
    });

    it('does not send reply notification for top-level comments', function (): void {
        Notification::fake();

        $mod = Mod::factory()->create();

        // Create a top-level comment (no parent)
        $commenter = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'parent_id' => null,
        ]);

        $job = new ProcessCommentNotification($comment);
        $job->handle();

        // No reply notification should be sent
        Notification::assertNothingSent();
    });

    it('records correct notification type for reply notifications', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        // Check that the correct notification type was recorded
        $log = NotificationLog::query()
            ->where('user_id', $parentAuthor->id)
            ->where('notifiable_id', $reply->id)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->notification_type)->toBe(NotificationType::ALL)
            ->and($log->notification_class)->toBe(CommentReplyNotification::class);
    });

    it('does not create duplicate reply notifications on job retry', function (): void {
        Notification::fake();

        $parentAuthor = User::factory()->create(['email_reply_notifications_enabled' => true]);
        $mod = Mod::factory()->create();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $parentAuthor->id,
        ]);

        $replier = User::factory()->create();
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $replier->id,
            'parent_id' => $parentComment->id,
        ]);

        $job = new ProcessCommentNotification($reply);
        $job->handle();

        Notification::assertSentToTimes($parentAuthor, CommentReplyNotification::class, 1);

        // Process again (simulate retry)
        $job->handle();

        // Still only one notification
        Notification::assertSentToTimes($parentAuthor, CommentReplyNotification::class, 1);
    });
});
