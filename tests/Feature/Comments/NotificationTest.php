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
use App\Notifications\NewCommentNotification;
use Carbon\Carbon;
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

    it('automatically subscribes commenter but not mod owner', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Create a comment from another user
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the commenter is subscribed
        expect(CommentSubscription::isSubscribed($commenter, $mod))->toBeTrue();

        // Assert that the owner is NOT automatically subscribed
        expect(CommentSubscription::isSubscribed($owner, $mod))->toBeFalse();
    });

    it('automatically subscribes commenter but not profile owner', function (): void {
        $user = User::factory()->create();

        // Create a comment from another user on the profile
        $commenter = User::factory()->create();
        Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $user->id,
            'user_id' => $commenter->id,
        ]);

        // Assert that the commenter is subscribed
        expect(CommentSubscription::isSubscribed($commenter, $user))->toBeTrue();

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

        $owner = User::factory()->create(['email_notifications_enabled' => false]);
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
        $owner = User::factory()->create(['email_notifications_enabled' => true]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        CommentSubscription::subscribe($owner, $mod);

        // Create additional subscribers with email notifications enabled
        $subscriber1 = User::factory()->create(['email_notifications_enabled' => true]);
        $subscriber2 = User::factory()->create(['email_notifications_enabled' => true]);

        // Create a subscriber with email notifications disabled
        $subscriberNoEmail = User::factory()->create(['email_notifications_enabled' => false]);

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

        // Verify the job was dispatched by the observer with a 5-minute delay
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job): bool => $job->comment->is($comment) && $job->delay instanceof Carbon);

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

        $owner = User::factory()->create(['email_notifications_enabled' => true]);
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);

        // Manually subscribe the owner for this test
        CommentSubscription::subscribe($owner, $mod);

        // Create subscribers with different email preferences
        $subscriberWithEmail = User::factory()->create(['email_notifications_enabled' => true]);
        $subscriberWithoutEmail = User::factory()->create(['email_notifications_enabled' => false]);

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

        // Assert that ProcessCommentNotification job was dispatched
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job) =>
            // Verify it's for the correct comment
            $job->comment->is($comment));

        // Also verify the job has a delay set (the delay is set in the constructor)
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job): bool =>
            // The delay property should be a Carbon instance set to 5 minutes from creation
            $job->delay instanceof Carbon);
    });
});
