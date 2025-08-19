<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Jobs\CheckCommentForSpam;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Services\CommentSpamChecker;
use App\Support\Akismet\SpamCheckResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
    $this->comment = Comment::factory()->for($this->mod, 'commentable')->create([
        'user_id' => $this->user->id,
    ]);

    // Enable Akismet for testing
    Config::set('akismet.enabled', true);
});

describe('Automatic Recheck Scheduling', function (): void {
    test('schedules recheck when Akismet returns recheck_after header', function (): void {
        Bus::fake();

        // Mock the spam checker to return a result with recheck_after
        $mockResult = new SpamCheckResult(
            isSpam: false,
            metadata: ['akismet_response' => 'false', 'recheck_after' => '3600'],
            recheckAfter: '3600' // 1 hour
        );

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldReceive('checkSpam')
            ->with($this->comment)
            ->once()
            ->andReturn($mockResult);

        $job = new CheckCommentForSpam($this->comment);
        $job->handle($mockSpamChecker);

        // Verify a delayed recheck job was dispatched
        Bus::assertDispatched(CheckCommentForSpam::class, fn ($job): bool => $job->comment->id === $this->comment->id &&
               $job->isRecheck === true);
    });

    test('does not schedule recheck when no recheck_after header', function (): void {
        Bus::fake();

        // Mock the spam checker to return a result without recheck_after
        $mockResult = new SpamCheckResult(
            isSpam: false,
            metadata: ['akismet_response' => 'false']
        );

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldReceive('checkSpam')
            ->with($this->comment)
            ->once()
            ->andReturn($mockResult);

        $job = new CheckCommentForSpam($this->comment);
        $job->handle($mockSpamChecker);

        // Verify only the original job was processed, no recheck scheduled
        Bus::assertNotDispatched(CheckCommentForSpam::class, fn ($job): bool => $job->isRecheck === true);
    });

    test('increments recheck counter on recheck jobs', function (): void {
        $mockResult = new SpamCheckResult(
            isSpam: false,
            metadata: ['akismet_response' => 'false']
        );

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldReceive('checkSpam')
            ->with($this->comment)
            ->once()
            ->andReturn($mockResult);

        // Initial recheck count should be 0
        expect($this->comment->spam_recheck_count)->toBe(0);

        // Run a recheck job
        $recheckJob = new CheckCommentForSpam($this->comment, isRecheck: true);
        $recheckJob->handle($mockSpamChecker);

        // Reload comment and verify counter was incremented
        $this->comment->refresh();
        expect($this->comment->spam_recheck_count)->toBe(1);
    });

    test('stops rechecking after maximum attempts reached', function (): void {
        // Set comment to already have max recheck attempts
        $maxAttempts = config('comments.spam.max_recheck_attempts', 3);
        $this->comment->update(['spam_recheck_count' => $maxAttempts]);

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldNotReceive('checkSpam');

        // Try to run another recheck - should be skipped
        $recheckJob = new CheckCommentForSpam($this->comment, isRecheck: true);
        $recheckJob->handle($mockSpamChecker);

        // Counter should remain at max attempts, not increment further
        $this->comment->refresh();
        expect($this->comment->spam_recheck_count)->toBe($maxAttempts);
    });

    test('does not schedule recheck when max attempts already reached', function (): void {
        Bus::fake();

        // Set comment to already have max recheck attempts
        $maxAttempts = config('comments.spam.max_recheck_attempts', 3);
        $this->comment->update(['spam_recheck_count' => $maxAttempts]);

        // Mock the spam checker - should not be called because job is skipped
        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldNotReceive('checkSpam');

        $recheckJob = new CheckCommentForSpam($this->comment, isRecheck: true);
        $recheckJob->handle($mockSpamChecker);

        // No new recheck job should be scheduled (and original job was skipped)
        Bus::assertNothingDispatched();
    });

    test('handles spam result with recheck scheduling', function (): void {
        Bus::fake();

        // Mock the spam checker to return spam with recheck_after
        $mockResult = new SpamCheckResult(
            isSpam: true,
            metadata: ['akismet_response' => 'true', 'recheck_after' => '7200'],
            recheckAfter: '7200' // 2 hours
        );

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldReceive('checkSpam')
            ->with($this->comment)
            ->once()
            ->andReturn($mockResult);

        $job = new CheckCommentForSpam($this->comment);
        $job->handle($mockSpamChecker);

        // Verify comment was marked as spam
        $this->comment->refresh();
        expect($this->comment->spam_status)->toBe(SpamStatus::SPAM);

        // Verify a delayed recheck job was still dispatched for spam comments
        Bus::assertDispatched(CheckCommentForSpam::class, fn ($job): bool => $job->comment->id === $this->comment->id &&
               $job->isRecheck === true);
    });
});

describe('Recheck Job Behavior', function (): void {
    test('recheck job skips already processed comment without isRecheck flag', function (): void {
        // Mark comment as already checked
        $this->comment->update([
            'spam_checked_at' => now(),
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldNotReceive('checkSpam');

        // Regular job should skip already checked comment
        $job = new CheckCommentForSpam($this->comment, isRecheck: false);
        $job->handle($mockSpamChecker);

        // Comment should remain unchanged
        $originalCheckedAt = $this->comment->spam_checked_at;
        $this->comment->refresh();
        expect($this->comment->spam_checked_at->eq($originalCheckedAt))->toBeTrue();
    });

    test('recheck job processes already checked comment with isRecheck flag', function (): void {
        // Mark comment as already checked
        $this->comment->update([
            'spam_checked_at' => now()->subHour(),
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $mockResult = new SpamCheckResult(
            isSpam: false,
            metadata: ['akismet_response' => 'false', 'recheck_reason' => 'scheduled_recheck']
        );

        $mockSpamChecker = Mockery::mock(CommentSpamChecker::class);
        $mockSpamChecker->shouldReceive('checkSpam')
            ->with($this->comment)
            ->once()
            ->andReturn($mockResult);

        // Recheck job should process already checked comment
        $recheckJob = new CheckCommentForSpam($this->comment, isRecheck: true);
        $recheckJob->handle($mockSpamChecker);

        // Comment should be updated with new check time
        $this->comment->refresh();
        expect($this->comment->spam_checked_at->isAfter(now()->subMinute()))->toBeTrue();
    });
});

describe('Helper Methods', function (): void {
    test('canBeRechecked returns true when under max attempts', function (): void {
        expect($this->comment->canBeRechecked())->toBeTrue();

        $this->comment->update(['spam_recheck_count' => 1]);
        expect($this->comment->canBeRechecked())->toBeTrue();

        $this->comment->update(['spam_recheck_count' => 2]);
        expect($this->comment->canBeRechecked())->toBeTrue();
    });

    test('canBeRechecked returns false when at max attempts', function (): void {
        $maxAttempts = config('comments.spam.max_recheck_attempts', 3);
        $this->comment->update(['spam_recheck_count' => $maxAttempts]);
        expect($this->comment->canBeRechecked())->toBeFalse();

        $this->comment->update(['spam_recheck_count' => $maxAttempts + 1]);
        expect($this->comment->canBeRechecked())->toBeFalse();
    });
});
