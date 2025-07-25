<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Services\CommentSpamChecker;
use App\Support\Akismet\SpamCheckResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job to check a comment for spam using Akismet.
 */
class CheckCommentForSpam implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of recheck attempts allowed.
     */
    private function getMaxRecheckAttempts(): int
    {
        return config('comments.spam.max_recheck_attempts', 3);
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comment $comment,
        public bool $isRecheck = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CommentSpamChecker $spamChecker): void
    {
        // Skip if already checked and this is not a recheck
        if ($this->comment->spam_checked_at !== null && ! $this->isRecheck) {
            return;
        }

        // For rechecks, verify we haven't exceeded max attempts
        $maxAttempts = $this->getMaxRecheckAttempts();
        if ($this->isRecheck && $this->comment->spam_recheck_count >= $maxAttempts) {
            Log::info('Skipping spam recheck - maximum attempts reached', [
                'comment_id' => $this->comment->id,
                'recheck_count' => $this->comment->spam_recheck_count,
                'max_attempts' => $maxAttempts,
            ]);

            return;
        }

        // Default to clean if spam checking is disabled.
        if (! config('akismet.enabled', false)) {
            $this->comment->markAsClean(metadata: ['reason' => 'akismet_disabled'], quiet: true);

            return;
        }

        // Perform spam check with Akismet.
        $result = $spamChecker->checkSpam($this->comment);

        // Increment recheck counter if this is a recheck
        if ($this->isRecheck) {
            $this->comment->spam_recheck_count++;
        }

        // Comment is clean.
        if ($result->getSpamStatus() === SpamStatus::CLEAN) {
            $this->comment->markAsClean($result->metadata, quiet: true);
            $this->scheduleRecheckIfNeeded($result);

            return;
        }

        // Comment is spam.
        $this->comment->markAsSpam($result, quiet: true);

        // Auto-delete high confidence spam.
        if ($result->shouldAutoDelete()) {
            $this->comment->delete();

            return;
        }

        // Schedule recheck if Akismet recommends it and we haven't exceeded max attempts
        $this->scheduleRecheckIfNeeded($result);
    }

    /**
     * Schedule a recheck job if Akismet recommends it and we haven't exceeded max attempts.
     */
    private function scheduleRecheckIfNeeded(SpamCheckResult $result): void
    {
        // Skip if no recheck recommendation
        if (! $result->recheckAfter) {
            return;
        }

        // Skip if we've already reached max recheck attempts
        $maxAttempts = $this->getMaxRecheckAttempts();
        if ($this->comment->spam_recheck_count >= $maxAttempts) {
            Log::info('Skipping spam recheck scheduling - maximum attempts reached', [
                'comment_id' => $this->comment->id,
                'recheck_count' => $this->comment->spam_recheck_count,
                'max_attempts' => $maxAttempts,
            ]);

            return;
        }

        try {
            // Parse the recheck_after value (should be in seconds)
            $recheckDelaySeconds = (int) $result->recheckAfter;
            $recheckAt = now()->addSeconds($recheckDelaySeconds);

            // Schedule the delayed recheck job
            self::dispatch($this->comment, isRecheck: true)
                ->delay($recheckAt);

            Log::info('Scheduled spam recheck', [
                'comment_id' => $this->comment->id,
                'recheck_delay_seconds' => $recheckDelaySeconds,
                'recheck_at' => $recheckAt->toISOString(),
                'current_recheck_count' => $this->comment->spam_recheck_count,
            ]);

        } catch (Throwable $throwable) {
            Log::error('Failed to schedule spam recheck', [
                'comment_id' => $this->comment->id,
                'recheck_after' => $result->recheckAfter,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
