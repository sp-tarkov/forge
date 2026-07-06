<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Enums\SpamStatus;
use App\Jobs\CheckCommentForSpam;
use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\Mod;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use App\Services\CommentSpamService;
use App\Support\Akismet\SpamCheckResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

describe('urls and tracking helpers', function (): void {
    it('returns null url when commentable is null and the correct url when it exists', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // The hash ID format includes the tab hash from the commentable
        $url = $comment->getUrl();
        expect($url)
            ->toBeString()
            ->toContain($mod->getCommentableUrl())
            ->toContain('comment-'.$comment->id);

        // Simulate a scenario where commentable is deleted/null
        $comment->setRelation('commentable', null);

        expect($comment->getUrl())->toBeNull();
    });

    it('returns the correct hash id with and without a commentable', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        expect($comment->getHashId())
            ->toBeString()
            ->toContain('comment-'.$comment->id);

        // Simulate a scenario where commentable is deleted/null
        $comment->setRelation('commentable', null);

        expect($comment->getHashId())->toBe('comment-'.$comment->id);
    });

    it('returns empty tracking url when commentable is null', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Simulate a scenario where commentable is deleted/null
        $comment->setRelation('commentable', null);

        expect($comment->getTrackingUrl())->toBe('');
    });

    it('returns the generic tracking title when commentable is null', function (): void {
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Simulate a scenario where commentable is deleted/null
        $comment->setRelation('commentable', null);

        expect($comment->getTrackingTitle())->toBe('Comment');
    });

    it('returns the user profile tracking title when commentable is a user', function (): void {
        $user = User::factory()->create(['name' => 'John Doe']);
        $comment = Comment::factory()->create([
            'commentable_id' => $user->id,
            'commentable_type' => User::class,
        ]);

        expect($comment->getTrackingTitle())->toBe("Comment on John Doe's profile");
    });
});

describe('body length validation', function (): void {
    it('cannot save comment with body exceeding max length', function (): void {
        $maxLength = config()->integer('comments.validation.max_length', 10000);
        $mod = Mod::factory()->create();
        $user = User::factory()->create();

        // Create a comment with body exceeding max length
        $longBody = str_repeat('a', $maxLength + 1);

        expect(fn () => Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $longBody,
        ]))->toThrow(InvalidArgumentException::class, sprintf('Comment body cannot exceed %d characters.', $maxLength));
    });

    it('can save comment at exactly max length', function (): void {
        $maxLength = config()->integer('comments.validation.max_length', 10000);
        $mod = Mod::factory()->create();
        $user = User::factory()->create();

        $bodyAtMaxLength = str_repeat('a', $maxLength);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $bodyAtMaxLength,
        ]);

        expect($comment)->toBeInstanceOf(Comment::class)
            ->and(mb_strlen($comment->body))->toBe($maxLength);
    });
});

describe('translated comment factory', function (): void {
    it('creates a comment with a translated version', function (): void {
        $comment = Comment::factory()->translated('ru')->create();

        $version = $comment->latestVersion;
        expect($version)->not->toBeNull()
            ->and($version?->isTranslated())->toBeTrue()
            ->and($version?->detected_language)->toBe('ru')
            ->and($version?->detected_language_name)->toBe('Russian')
            ->and($version?->translated_body)->not->toBeNull()
            ->and($version?->language_detected_at)->not->toBeNull()
            ->and($version?->translated_at)->not->toBeNull()
            ->and($version?->translated_body_html)->toContain('<p>');
    });

    it('picks a random sample language when none is given', function (): void {
        $comment = Comment::factory()->translated()->create();

        $version = $comment->latestVersion;
        expect($version?->isTranslated())->toBeTrue()
            ->and($version?->detected_language)->toBeIn(['ru', 'de', 'fr', 'zh']);
    });

    it('adds the translated version after an existing initial version', function (): void {
        $comment = Comment::factory()->withVersion('Original English body.')->translated('de')->create();

        expect($comment->versions()->count())->toBe(2)
            ->and($comment->latestVersion?->detected_language)->toBe('de')
            ->and($comment->latestVersion?->isTranslated())->toBeTrue();
    });
});

describe('spam status helpers', function (): void {
    beforeEach(function (): void {
        Config::set('akismet.enabled', false);
    });

    it('correctly identifies spam comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::SPAM,
        ]);

        // Manually set spam status after creation to avoid observer interference
        $comment->save();
        $comment->update(['spam_status' => SpamStatus::SPAM]);

        expect($comment->isSpam())->toBeTrue();
        expect($comment->isSpamClean())->toBeFalse();
        expect($comment->isPendingSpamCheck())->toBeFalse();
    });

    it('correctly identifies clean comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect($comment->isSpam())->toBeFalse();
        expect($comment->isSpamClean())->toBeTrue();
        expect($comment->isPendingSpamCheck())->toBeFalse();
    });
});

describe('spam scopes', function (): void {
    beforeEach(function (): void {
        Config::set('akismet.enabled', false);
    });

    it('filters comments by spam status correctly', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $spamComment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        $spamComment->save();
        $spamComment->update(['spam_status' => SpamStatus::SPAM]);

        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect(Comment::spam()->count())->toBe(1);
        expect(Comment::clean()->count())->toBe(1); // just cleanComment
    });

    it('filters out spam comments from the commentable display scopes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        // Create clean comment (will be set to clean by observer)
        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is a clean comment',
        ]);

        // Create spam comment and manually set it to spam
        $spamComment = Comment::factory()->withVersion('This is spam')->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        // Update spam status after creation to avoid observer resetting it
        $spamComment->update(['spam_status' => SpamStatus::SPAM]);

        // Check that only clean comments are displayed
        expect($mod->comments()->clean()->count())->toBe(1);
        expect($mod->comments()->spam()->count())->toBe(1);
        expect($mod->rootComments()->clean()->count())->toBe(1);
    });
});

describe('spam check result value object', function (): void {
    it('works correctly with spam status', function (): void {
        $result = new SpamCheckResult(
            isSpam: true,
            metadata: ['test' => 'data']
        );

        expect($result->isSpam)->toBeTrue();
        expect($result->metadata)->toBe(['test' => 'data']);
        expect($result->getSpamStatus())->toBe(SpamStatus::SPAM);
    });

    it('determines auto-deletion correctly', function (): void {
        // Test with discard flag set to true
        $discardResult = new SpamCheckResult(
            isSpam: true,
            metadata: [],
            discard: true
        );

        // Test with discard flag set to false
        $noDiscardResult = new SpamCheckResult(
            isSpam: true,
            metadata: [],
            discard: false
        );

        expect($discardResult->shouldAutoDelete())->toBeTrue();
        expect($noDiscardResult->shouldAutoDelete())->toBeFalse();
    });
});

describe('comment observer spam behavior', function (): void {
    it('returns not spam when Akismet is disabled', function (): void {
        Config::set('akismet.enabled', false);

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        $spamChecker = resolve(CommentSpamService::class);
        $result = $spamChecker->checkSpam($comment);

        expect($result->isSpam)->toBeFalse();
        expect($result->metadata)->toHaveKey('reason', 'akismet_disabled');
    });

    it('sets comments to clean by default when Akismet is disabled', function (): void {
        Config::set('akismet.enabled', false);

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->withVersion('This is a test comment')->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        // Process jobs synchronously for testing
        Queue::fake();
        dispatch_sync(new CheckCommentForSpam($comment));

        // Refresh comment from database
        $comment->refresh();

        expect($comment->spam_status)->toBe(SpamStatus::CLEAN);
    });

    it('skips dispatching CheckCommentForSpam on create when Akismet is disabled', function (): void {
        Config::set('akismet.enabled', false);
        Queue::fake();

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        // Seed the comment in the PENDING state so the observer's inline disabled-path branch runs. The factory defaults
        // to CLEAN, which short-circuits the observer guard.
        $comment = Comment::factory()->withVersion('Body content')->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::PENDING,
        ]);

        $comment->refresh();

        expect($comment->spam_status)->toBe(SpamStatus::CLEAN);
        expect($comment->spam_metadata)->toBe(['reason' => 'akismet_disabled']);
        Queue::assertNotPushed(CheckCommentForSpam::class);
    });

    it('marks comments clean inline when created through Eloquent without an explicit spam_status', function (): void {
        // Reproduces the production path used by the comment Livewire component, which calls
        // $commentable->comments()->create([...]) without specifying spam_status. The DB default of 'pending' fills the
        // row on insert, but the in-memory model attribute stays null until refresh - so the observer must treat null
        // the same as PENDING when Akismet is off, otherwise the row is left stuck in the PENDING state.
        Config::set('akismet.enabled', false);
        Queue::fake();

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = $mod->comments()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'user_ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'referrer' => '',
        ]);

        $comment->refresh();

        expect($comment->spam_status)->toBe(SpamStatus::CLEAN);
        expect($comment->spam_metadata)->toBe(['reason' => 'akismet_disabled']);
        Queue::assertNotPushed(CheckCommentForSpam::class);
    });

    it('dispatches CheckCommentForSpam on create when Akismet is enabled', function (): void {
        Config::set('akismet.enabled', true);
        Queue::fake();

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->withVersion('Body content')->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        Queue::assertPushed(CheckCommentForSpam::class, fn (CheckCommentForSpam $job): bool => $job->comment->id === $comment->id);
    });
});

describe('comment notifications', function (): void {
    it('triggers spam check job on comment creation', function (): void {
        // The observer only dispatches CheckCommentForSpam while Akismet is enabled; otherwise it marks the comment
        // clean inline and never queues a job for the bus fake to capture.
        Config::set('akismet.enabled', true);
        Bus::fake();

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        Bus::assertDispatched(CheckCommentForSpam::class, fn (CheckCommentForSpam $job): bool => $job->comment->is($comment));
    });

    it('does not auto-subscribe commenter when commenting on a mod', function (): void {
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

    it('does not auto-subscribe commenter on a user profile', function (): void {
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

    it('sends notification to subscribers after the delay window', function (): void {
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

    it('dispatches comment notification job on creation', function (): void {
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
        Bus::assertDispatched(ProcessCommentNotification::class, fn ($job): bool =>
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

describe('reply notifications', function (): void {
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
