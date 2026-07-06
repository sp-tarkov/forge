<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CommentTranslator;
use App\Models\CommentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job to poll an Anthropic translation batch and apply its results once processing has ended.
 */
#[Timeout(120)]
#[Backoff([30, 60, 120])]
#[Tries(3)]
final class FetchCommentTranslationBatch implements ShouldQueue
{
    use Queueable;

    /**
     * The maximum number of polls before giving up on a batch. Batches expire after 24 hours; this allows just over
     * that at the five-minute poll interval.
     */
    private const int MAX_POLLS = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $batchId,
        public int $pollCount = 0,
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
    public function handle(CommentTranslator $commentTranslator): void
    {
        if (! $commentTranslator->isBatchComplete($this->batchId)) {
            if ($this->pollCount >= self::MAX_POLLS) {
                Log::error('Comment translation batch never completed', [
                    'batch_id' => $this->batchId,
                    'polls' => $this->pollCount,
                ]);

                return;
            }

            dispatch(new self($this->batchId, $this->pollCount + 1))->delay(now()->addMinutes(5));

            return;
        }

        $applied = 0;
        $failed = 0;

        foreach ($commentTranslator->getBatchResults($this->batchId) as $versionId => $result) {
            $version = CommentVersion::query()->find((int) $versionId);
            if ($version === null) {
                continue;
            }

            if ($result->isError()) {
                $failed++;
                Log::warning('Comment translation batch item failed', [
                    'batch_id' => $this->batchId,
                    'comment_version_id' => $versionId,
                    'metadata' => $result->metadata,
                ]);

                continue;
            }

            $version->applyTranslationResult($result);
            $applied++;
        }

        Log::info('Comment translation batch applied', [
            'batch_id' => $this->batchId,
            'applied' => $applied,
            'failed' => $failed,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('FetchCommentTranslationBatch job failed', [
            'batch_id' => $this->batchId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
