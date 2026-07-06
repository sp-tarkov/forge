<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CommentTranslator;
use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Services\LanguageDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job to detect languages for comment versions that were never processed and submit every untranslated
 * non-English version to the Anthropic Batch API for bulk translation. The scan runs in bounded slices, with each
 * job execution processing one slice and re-dispatching itself for the next, so no single execution outruns the
 * queue's retry window.
 */
#[Timeout(120)]
#[Tries(1)]
final class BackfillCommentTranslations implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $afterCommentId = 0
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LanguageDetectionService $languageDetectionService, CommentTranslator $commentTranslator): void
    {
        if (! config()->boolean('comments.translation.enabled', false)) {
            return;
        }

        /** @var array<int, string> $pending */
        $pending = [];

        // On the first slice only, gather stragglers: latest versions with a previously detected non-English
        // language that still lack a translation. Later slices skip this since the set does not change mid-scan.
        if ($this->afterCommentId === 0) {
            $targetLanguage = config()->string('comments.translation.target_language', 'en');
            $this->translatableComments()
                ->whereHas('latestVersion', fn (Builder $query) => $query
                    ->whereNotNull('detected_language')
                    ->where('detected_language', '!=', $targetLanguage)
                    ->whereNull('translated_body'))
                ->chunkById(200, function (Collection $comments) use (&$pending): void {
                    foreach ($comments as $comment) {
                        $version = $comment->latestVersion;
                        if ($version === null) {
                            continue;
                        }

                        $pending[$version->id] = $version->body;
                    }
                });
        }

        // Run local detection over one slice of latest versions that have never been processed. Confident English
        // versions are marked inline; everything uncertain or non-English is queued for the translation batch.
        $scanChunk = max(1, config()->integer('comments.translation.scan_chunk', 2500));
        $comments = $this->translatableComments()
            ->where('comments.id', '>', $this->afterCommentId)
            ->whereHas('latestVersion', fn (Builder $query) => $query->whereNull('language_detected_at'))
            ->orderBy('comments.id')
            ->limit($scanChunk)
            ->get();

        foreach ($comments as $comment) {
            $version = $comment->latestVersion;
            if ($version === null) {
                continue;
            }
            if ($version->language_detected_at !== null) {
                continue;
            }

            $detection = $languageDetectionService->detect($version->body);

            if ($detection->tooShort) {
                $version->markLanguageDetected(null, ['detector' => 'eld', 'reason' => 'too_short']);

                continue;
            }

            if ($detection->isConfidentEnglish()) {
                $version->markLanguageDetected('en', ['detector' => 'eld', 'scores' => $detection->scores]);

                continue;
            }

            $pending[$version->id] = $version->body;
        }

        $this->submitPending($pending, $commentTranslator);

        // Continue scanning from the last comment in this slice, or finish when the slice came up short.
        if ($comments->count() === $scanChunk) {
            $lastComment = $comments->last();
            if ($lastComment !== null) {
                dispatch(new self($lastComment->id));

                return;
            }
        }

        Log::info('Comment translation backfill scan complete', [
            'last_comment_id' => $this->afterCommentId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('BackfillCommentTranslations job failed', [
            'after_comment_id' => $this->afterCommentId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Submit the slice's pending version bodies to the translator in batches.
     *
     * @param  array<int, string>  $pending
     */
    private function submitPending(array $pending, CommentTranslator $commentTranslator): void
    {
        if ($pending === []) {
            return;
        }

        // Leave the pending versions unprocessed so a later run can translate them once credentials exist.
        if (! $commentTranslator->isConfigured()) {
            Log::warning('Comment translation backfill skipped batch submission because the translator is not configured', [
                'versions' => count($pending),
            ]);

            return;
        }

        $batchSize = max(1, config()->integer('comments.translation.batch_size', 100));
        $batchCount = 0;

        foreach (array_chunk($pending, $batchSize, preserve_keys: true) as $chunk) {
            $batchId = $commentTranslator->submitBatch($chunk);
            if ($batchId === null) {
                continue;
            }

            dispatch(new FetchCommentTranslationBatch($batchId))->delay(now()->addMinutes(2));
            $batchCount++;
        }

        Log::info('Comment translation backfill submitted batches', [
            'versions' => count($pending),
            'batches' => $batchCount,
        ]);
    }

    /**
     * Base query for comments whose latest version is eligible for translation.
     *
     * @return Builder<Comment>
     */
    private function translatableComments(): Builder
    {
        return Comment::query()
            ->whereNull('deleted_at')
            ->where('spam_status', '!=', SpamStatus::SPAM->value);
    }
}
