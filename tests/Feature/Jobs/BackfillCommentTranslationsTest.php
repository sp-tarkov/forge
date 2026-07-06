<?php

declare(strict_types=1);

use App\Contracts\CommentTranslator;
use App\Enums\SpamStatus;
use App\Jobs\BackfillCommentTranslations;
use App\Jobs\FetchCommentTranslationBatch;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Services\LanguageDetectionService;
use App\Support\DataTransferObjects\CommentTranslationResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

/**
 * Create a comment for backfill scenarios while translation is disabled, so the observer leaves it unprocessed.
 *
 * @param  array<string, mixed>  $attributes
 */
function createBackfillComment(string $body, array $attributes = []): Comment
{
    return Comment::factory()
        ->for(test()->mod, 'commentable')
        ->create([...$attributes, 'user_id' => test()->user->id, 'body' => $body]);
}

describe('Backfill', function (): void {
    test('detects languages locally and submits pending versions in a batch', function (): void {
        $english = createBackfillComment('Hello there, this is a great mod and I love using it every day! Thank you for your work.');
        $short = createBackfillComment('gg');
        $russian = createBackfillComment('Привет, отличный мод! Спасибо за вашу работу, продолжай в том же духе.');

        $detectedPending = createBackfillComment('Ещё один комментарий, который уже был определён ранее.');
        $detectedPending->latestVersion?->markLanguageDetected('ru', ['detector' => 'eld']);

        $translated = createBackfillComment('Этот комментарий уже переведён.');
        $translated->latestVersion?->applyTranslationResult(new CommentTranslationResult(
            detectedLanguage: 'ru',
            translatedBody: 'This comment is already translated.',
            metadata: ['provider' => 'anthropic'],
        ));

        $deleted = createBackfillComment('Удалённый комментарий, который не нужно переводить.', ['deleted_at' => now()]);
        $spam = createBackfillComment('Спам-комментарий, который нужно перевести для проверки администраторами.', ['spam_status' => SpamStatus::SPAM]);

        Config::set('comments.translation.enabled', true);
        Bus::fake([FetchCommentTranslationBatch::class]);

        $expectedVersionIds = collect([$russian, $detectedPending, $spam])
            ->map(fn (Comment $comment): int => (int) $comment->latestVersion?->id)
            ->sort()
            ->values()
            ->all();

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('submitBatch')
            ->once()
            ->withArgs(function (array $bodies) use ($expectedVersionIds): bool {
                $keys = array_keys($bodies);
                sort($keys);

                return $keys === $expectedVersionIds;
            })
            ->andReturn('msgbatch_backfill');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertDispatched(FetchCommentTranslationBatch::class, fn (FetchCommentTranslationBatch $job): bool => $job->batchId === 'msgbatch_backfill' && $job->pollCount === 0);

        expect($english->latestVersion?->refresh()->detected_language)->toBe('en')
            ->and($short->latestVersion?->refresh()->language_detected_at)->not->toBeNull()
            ->and($short->latestVersion?->refresh()->detected_language)->toBeNull()
            ->and($russian->latestVersion?->refresh()->language_detected_at)->toBeNull()
            ->and($deleted->latestVersion?->refresh()->language_detected_at)->toBeNull()
            ->and($spam->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('includes spam comments so administrators can review them in English', function (): void {
        $spam = createBackfillComment('Спам-комментарий на русском языке, который администраторы должны проверить.', ['spam_status' => SpamStatus::SPAM]);

        Config::set('comments.translation.enabled', true);
        Bus::fake([FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('submitBatch')
            ->once()
            ->withArgs(fn (array $bodies): bool => array_keys($bodies) === [(int) $spam->latestVersion?->id])
            ->andReturn('msgbatch_spam');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertDispatched(FetchCommentTranslationBatch::class, fn (FetchCommentTranslationBatch $job): bool => $job->batchId === 'msgbatch_spam');
    });

    test('splits submissions into batches of the configured size', function (): void {
        createBackfillComment('Привет, отличный мод! Спасибо за вашу работу.');
        createBackfillComment('Отличная работа, мод работает просто прекрасно!');

        Config::set('comments.translation.enabled', true);
        Config::set('comments.translation.batch_size', 1);
        Bus::fake([FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('submitBatch')
            ->twice()
            ->andReturn('msgbatch_one', 'msgbatch_two');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertDispatched(FetchCommentTranslationBatch::class, fn (FetchCommentTranslationBatch $job): bool => $job->batchId === 'msgbatch_one');
        Bus::assertDispatched(FetchCommentTranslationBatch::class, fn (FetchCommentTranslationBatch $job): bool => $job->batchId === 'msgbatch_two');
    });

    test('continues scanning across slices and re-dispatches itself with a cursor', function (): void {
        createBackfillComment('Привет, отличный мод! Спасибо за вашу работу.');
        $second = createBackfillComment('Ещё один комментарий, который нужно перевести на английский.');
        createBackfillComment('Hello there, this is a great mod and I love using it every day!');

        Config::set('comments.translation.enabled', true);
        Config::set('comments.translation.scan_chunk', 2);
        Bus::fake([BackfillCommentTranslations::class, FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('submitBatch')->once()->andReturn('msgbatch_slice');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertDispatched(BackfillCommentTranslations::class, fn (BackfillCommentTranslations $job): bool => $job->afterCommentId === $second->id);
    });

    test('runs local detection but skips submission when the translator is not configured', function (): void {
        $english = createBackfillComment('Hello there, this is a great mod and I love using it every day! Thank you for your work.');
        $russian = createBackfillComment('Привет, отличный мод! Спасибо за вашу работу, продолжай в том же духе.');

        Config::set('comments.translation.enabled', true);
        Bus::fake([FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(false);
        $translator->shouldNotReceive('submitBatch');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertNotDispatched(FetchCommentTranslationBatch::class);

        expect($english->latestVersion?->refresh()->detected_language)->toBe('en')
            ->and($russian->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('does nothing when translation is disabled', function (): void {
        createBackfillComment('Привет, отличный мод! Спасибо за вашу работу.');

        Bus::fake([FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('submitBatch');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertNotDispatched(FetchCommentTranslationBatch::class);
    });

    test('submits nothing when every comment is already processed', function (): void {
        createBackfillComment('Hello there, this is a great mod and I love using it every day! Thank you for your work.');

        Config::set('comments.translation.enabled', true);
        Bus::fake([FetchCommentTranslationBatch::class]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('submitBatch');

        (new BackfillCommentTranslations)->handle(resolve(LanguageDetectionService::class), $translator);

        Bus::assertNotDispatched(FetchCommentTranslationBatch::class);
    });
});
