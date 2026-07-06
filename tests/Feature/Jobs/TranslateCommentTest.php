<?php

declare(strict_types=1);

use App\Contracts\CommentTranslator;
use App\Enums\SpamStatus;
use App\Jobs\TranslateComment;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Services\LanguageDetectionService;
use App\Support\DataTransferObjects\CommentTranslationResult;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

const ENGLISH_COMMENT_BODY = 'Hello there, this is a great mod and I love using it every day! Thank you for your work.';
const RUSSIAN_COMMENT_BODY = 'Привет, отличный мод! Спасибо за вашу работу, продолжай в том же духе.';

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

/**
 * Create a comment while translation is disabled so the observer does not process it, then enable translation for
 * the behavior under test.
 */
function createCommentForTranslation(string $body): Comment
{
    $comment = Comment::factory()
        ->for(test()->mod, 'commentable')
        ->create(['user_id' => test()->user->id, 'body' => $body]);

    Config::set('comments.translation.enabled', true);

    return $comment;
}

describe('Translation Job', function (): void {
    test('is throttled by the anthropic-api rate limiter', function (): void {
        $comment = createCommentForTranslation(ENGLISH_COMMENT_BODY);

        $middleware = new TranslateComment($comment)->middleware();

        expect($middleware)->toHaveCount(1)
            ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
            ->and(new ReflectionProperty(RateLimited::class, 'limiterName')->getValue($middleware[0]))->toBe('anthropic-api');
    });

    test('does nothing when translation is disabled', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);
        Config::set('comments.translation.enabled', false);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('marks confident English comments without calling the translator', function (): void {
        $comment = createCommentForTranslation(ENGLISH_COMMENT_BODY);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        $version = $comment->latestVersion?->refresh();
        expect($version?->detected_language)->toBe('en')
            ->and($version?->language_detected_at)->not->toBeNull()
            ->and($version?->translated_body)->toBeNull()
            ->and($version?->translation_metadata)->toHaveKey('detector', 'eld');
    });

    test('marks short comments as undetermined without calling the translator', function (): void {
        $comment = createCommentForTranslation('gg');

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        $version = $comment->latestVersion?->refresh();
        expect($version?->detected_language)->toBeNull()
            ->and($version?->language_detected_at)->not->toBeNull()
            ->and($version?->translation_metadata)->toHaveKey('reason', 'too_short');
    });

    test('stores the translation for a non-English comment', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('translate')
            ->once()
            ->with(RUSSIAN_COMMENT_BODY)
            ->andReturn(new CommentTranslationResult(
                detectedLanguage: 'ru',
                translatedBody: 'Hi, great mod! Thanks for your work, keep it up.',
                metadata: ['provider' => 'anthropic'],
            ));

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        $version = $comment->latestVersion?->refresh();
        expect($version?->detected_language)->toBe('ru')
            ->and($version?->translated_body)->toBe('Hi, great mod! Thanks for your work, keep it up.')
            ->and($version?->language_detected_at)->not->toBeNull()
            ->and($version?->translated_at)->not->toBeNull()
            ->and($version?->isTranslated())->toBeTrue();
    });

    test('stores no translation when the translator confirms English', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('translate')
            ->once()
            ->andReturn(new CommentTranslationResult(
                detectedLanguage: 'en',
                translatedBody: null,
                metadata: ['provider' => 'anthropic'],
            ));

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        $version = $comment->latestVersion?->refresh();
        expect($version?->detected_language)->toBe('en')
            ->and($version?->translated_body)->toBeNull()
            ->and($version?->translated_at)->toBeNull()
            ->and($version?->language_detected_at)->not->toBeNull();
    });

    test('leaves the version unprocessed when the translator returns an error result', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(true);
        $translator->shouldReceive('translate')
            ->once()
            ->andReturn(new CommentTranslationResult(
                detectedLanguage: null,
                translatedBody: null,
                metadata: ['error' => 'refusal'],
            ));

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('skips the API call when the translator is not configured', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldReceive('isConfigured')->andReturn(false);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('skips deleted comments', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);
        $comment->update(['deleted_at' => now()]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('skips spam comments', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);
        $comment->update(['spam_status' => SpamStatus::SPAM]);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->language_detected_at)->toBeNull();
    });

    test('skips versions that were already processed', function (): void {
        $comment = createCommentForTranslation(RUSSIAN_COMMENT_BODY);
        $comment->latestVersion?->markLanguageDetected('ru', ['detector' => 'eld']);

        $translator = Mockery::mock(CommentTranslator::class);
        $translator->shouldNotReceive('translate');

        new TranslateComment($comment)->handle(resolve(LanguageDetectionService::class), $translator);

        expect($comment->latestVersion?->refresh()->detected_language)->toBe('ru');
    });
});

describe('Dispatching', function (): void {
    test('is dispatched when a comment is created and translation is enabled', function (): void {
        Config::set('comments.translation.enabled', true);
        Bus::fake();

        $comment = Comment::factory()
            ->for($this->mod, 'commentable')
            ->create(['user_id' => $this->user->id, 'body' => RUSSIAN_COMMENT_BODY]);

        Bus::assertDispatched(TranslateComment::class, fn (TranslateComment $job): bool => $job->comment->id === $comment->id);
    });

    test('is not dispatched when translation is disabled', function (): void {
        Bus::fake();

        Comment::factory()
            ->for($this->mod, 'commentable')
            ->create(['user_id' => $this->user->id, 'body' => RUSSIAN_COMMENT_BODY]);

        Bus::assertNotDispatched(TranslateComment::class);
    });
});
