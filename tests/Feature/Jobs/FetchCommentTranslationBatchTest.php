<?php

declare(strict_types=1);

use App\Contracts\CommentTranslator;
use App\Jobs\FetchCommentTranslationBatch;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Support\DataTransferObjects\CommentTranslationResult;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

describe('Batch Polling', function (): void {
    test('is throttled by the anthropic-api rate limiter', function (): void {
        $middleware = new FetchCommentTranslationBatch('msgbatch_test')->middleware();

        expect($middleware)->toHaveCount(1)
            ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
            ->and(new ReflectionProperty(RateLimited::class, 'limiterName')->getValue($middleware[0]))->toBe('anthropic-api');
    });

    test('re-dispatches itself while the batch is still processing', function (): void {
        Bus::fake();

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isBatchComplete')->once()->with('msgbatch_test')->andReturn(false);
        $translator->shouldNotReceive('getBatchResults');

        new FetchCommentTranslationBatch('msgbatch_test')->handle($translator);

        Bus::assertDispatched(FetchCommentTranslationBatch::class, fn (FetchCommentTranslationBatch $job): bool => $job->batchId === 'msgbatch_test' && $job->pollCount === 1);
    });

    test('gives up after the maximum number of polls', function (): void {
        Bus::fake();

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isBatchComplete')->once()->andReturn(false);

        new FetchCommentTranslationBatch('msgbatch_test', 300)->handle($translator);

        Bus::assertNotDispatched(FetchCommentTranslationBatch::class);
    });
});

describe('Applying Results', function (): void {
    test('applies completed batch results to the comment versions', function (): void {
        $translatedComment = Comment::factory()
            ->for($this->mod, 'commentable')
            ->create(['user_id' => $this->user->id, 'body' => 'Привет, отличный мод!']);
        $failedComment = Comment::factory()
            ->for($this->mod, 'commentable')
            ->create(['user_id' => $this->user->id, 'body' => 'Ещё один комментарий.']);

        $translatedVersionId = (string) $translatedComment->latestVersion?->id;
        $failedVersionId = (string) $failedComment->latestVersion?->id;

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isBatchComplete')->once()->with('msgbatch_test')->andReturn(true);
        $translator->shouldReceive('getBatchResults')->once()->with('msgbatch_test')->andReturn([
            $translatedVersionId => new CommentTranslationResult(
                detectedLanguage: 'ru',
                translatedBody: 'Hello, great mod!',
                metadata: ['provider' => 'anthropic'],
            ),
            $failedVersionId => new CommentTranslationResult(
                detectedLanguage: null,
                translatedBody: null,
                metadata: ['error' => 'batch_errored'],
            ),
            '999999999' => new CommentTranslationResult(
                detectedLanguage: 'de',
                translatedBody: 'A result for a version that no longer exists.',
                metadata: ['provider' => 'anthropic'],
            ),
        ]);

        new FetchCommentTranslationBatch('msgbatch_test')->handle($translator);

        $translatedVersion = $translatedComment->latestVersion?->refresh();
        expect($translatedVersion?->detected_language)->toBe('ru')
            ->and($translatedVersion?->translated_body)->toBe('Hello, great mod!')
            ->and($translatedVersion?->translated_at)->not->toBeNull()
            ->and($failedComment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });
});
