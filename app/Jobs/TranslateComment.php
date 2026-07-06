<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CommentTranslator;
use App\Models\Comment;
use App\Services\LanguageDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job to detect a comment's language and translate it into English when needed.
 */
#[Timeout(120)]
#[Backoff([5, 30, 60])]
#[Tries(3)]
final class TranslateComment implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comment $comment
    ) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('anthropic-api')];
    }

    /**
     * Execute the job.
     */
    public function handle(LanguageDetectionService $languageDetectionService, CommentTranslator $commentTranslator): void
    {
        if (! config()->boolean('comments.translation.enabled', false)) {
            return;
        }

        if ($this->comment->isDeleted() || $this->comment->isSpam()) {
            return;
        }

        $version = $this->comment->latestVersion;
        if ($version === null || $version->language_detected_at !== null) {
            return;
        }

        $detection = $languageDetectionService->detect($version->body);

        if ($detection->tooShort) {
            $version->markLanguageDetected(null, ['detector' => 'eld', 'reason' => 'too_short']);

            return;
        }

        if ($detection->isConfidentEnglish()) {
            $version->markLanguageDetected('en', ['detector' => 'eld', 'scores' => $detection->scores]);

            return;
        }

        // Leave the version unprocessed so a later backfill can translate it once credentials exist.
        if (! $commentTranslator->isConfigured()) {
            Log::info('Comment translation skipped because the translator is not configured', [
                'comment_id' => $this->comment->id,
                'comment_version_id' => $version->id,
            ]);

            return;
        }

        $result = $commentTranslator->translate($version->body);

        if ($result->isError()) {
            Log::warning('Comment translation returned an unusable result', [
                'comment_id' => $this->comment->id,
                'comment_version_id' => $version->id,
                'metadata' => $result->metadata,
            ]);

            return;
        }

        $version->applyTranslationResult($result);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('TranslateComment job failed', [
            'comment_id' => $this->comment->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
