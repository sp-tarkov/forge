<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Services\CommentSpamChecker;
use App\Support\Akismet\SpamCheckResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $apiKey = config('akismet.api_key');
    $blogUrl = config('akismet.blog_url');

    // Check if API credentials are available and look like valid Akismet keys
    if (empty($apiKey) || empty($blogUrl)) {
        $this->markTestSkipped('Akismet API key not set');
    }

    // Set configuration for testing
    Config::set('akismet.enabled', true);
    Config::set('akismet.api_key', $apiKey);
    Config::set('akismet.blog_url', $blogUrl);
    Config::set('app.env', 'testing');
});

describe('API key verification', function (): void {
    it('verifies API key is valid', function (): void {
        $spamChecker = resolve(CommentSpamChecker::class);

        $isValid = $spamChecker->verifyKey();

        expect($isValid)->toBeTrue();
    });

    it('verifies API key is invalid with wrong key', function (): void {
        Config::set('akismet.api_key', 'invalid-key-12345');

        $spamChecker = resolve(CommentSpamChecker::class);

        $isValid = $spamChecker->verifyKey();

        expect($isValid)->toBeFalse();
    });
});

describe('spam detection', function (): void {
    it('detects guaranteed spam comment using Akismet test data', function (): void {
        $user = User::factory()->create([
            'name' => 'akismet-guaranteed-spam',
            'email' => 'akismet-guaranteed-spam@example.com',
        ]);
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is spam content for testing',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Agent',
            'referrer' => 'https://example.com',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        expect($result->isSpam)->toBeTrue()
            ->and($result->metadata)->toHaveKey('akismet_response', 'true')
            ->and($result->metadata)->toHaveKey('checked_at')
            ->and($result->metadata)->toHaveKey('api_version', '1.1');
    });

    it('detects not-spam comment correctly', function (): void {
        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
        ]);
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is a legitimate comment about the mod',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Agent',
            'referrer' => 'https://example.com',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        expect($result->isSpam)->toBeFalse()
            ->and($result->metadata)->toHaveKey('akismet_response', 'false')
            ->and($result->discard)->toBeFalse();
    });
});

describe('spam/ham reporting', function (): void {
    it('submits spam report successfully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This comment was incorrectly marked as ham',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Agent',
            'referrer' => 'https://example.com',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);

        // This should not throw an exception
        $spamChecker->markAsSpam($comment);

        // If we get here, no exception was thrown
        expect(true)->toBeTrue();
    });

    it('submits ham report successfully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This legitimate comment was incorrectly marked as spam',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Agent',
            'referrer' => 'https://example.com',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);

        // This should not throw an exception
        $spamChecker->markAsHam($comment);

        // If we get here, no exception was thrown
        expect(true)->toBeTrue();
    });
});

describe('API usage tracking', function (): void {
    it('retrieves usage limit information', function (): void {
        $spamChecker = resolve(CommentSpamChecker::class);

        $usage = $spamChecker->getUsageLimit();

        expect($usage)->toBeArray()
            ->and($usage)->toHaveKeys(['limit', 'usage', 'percentage', 'throttled'])
            ->and($usage['throttled'])->toBeBool()
            ->and($usage['percentage'])->not->toBeNull();
    });
});

describe('error handling', function (): void {
    it('handles API errors gracefully', function (): void {
        // Use a completely invalid API key that will fail verification
        Config::set('akismet.api_key', 'definitely-invalid-key-that-will-fail');

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Test comment',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'referrer' => '',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        // Should return safe default (not spam) when an API key is invalid
        expect($result->isSpam)->toBeFalse()
            ->and($result->metadata)->toHaveKey('error', 'invalid_api_key');
    });

    it('includes test flag in non-production environment', function (): void {
        Config::set('app.env', 'testing');

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Test comment',
            'user_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'referrer' => '',
        ]);

        // We can't easily verify the is_test parameter is sent,
        // but we can verify the request completes successfully in test mode
        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        expect($result)->toBeInstanceOf(SpamCheckResult::class)
            ->and($result->metadata)->toHaveKey('api_version', '1.1');
    });

    it('handles missing request data gracefully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        // Create a comment with empty request data
        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Test comment without request data',
            'user_ip' => '',
            'user_agent' => '',
            'referrer' => '',
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        // Should still work with empty request data
        expect($result)->toBeInstanceOf(SpamCheckResult::class)
            ->and($result->metadata)->toHaveKey('akismet_response');
    });
});

describe('caching behavior', function (): void {
    it('caches key verification results', function (): void {
        $spamChecker = resolve(CommentSpamChecker::class);

        // Clear cache first
        Cache::forget('akismet:verify_key:'.config('akismet.api_key'));

        // The first call should hit the API
        $firstResult = $spamChecker->verifyKey();

        // Mock the HTTP client to ensure the second call doesn't hit API
        Http::fake(function (): void {
            throw new Exception('Should not make API call - should use cache');
        });

        // The second call should use cache
        $secondResult = $spamChecker->verifyKey();

        expect($firstResult)->toBe($secondResult)
            ->and($secondResult)->toBeTrue();
    });
});
