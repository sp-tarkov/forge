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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('akismet.enabled', false);
});

describe('spam status methods', function (): void {
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

describe('comment scopes', function (): void {
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

        $cleanComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect(Comment::spam()->count())->toBe(1);
        expect(Comment::clean()->count())->toBe(1); // just cleanComment
    });
});

describe('spam checker behavior', function (): void {
    it('returns not spam when Akismet is disabled', function (): void {
        Config::set('akismet.enabled', false);

        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        $spamChecker = resolve(CommentSpamChecker::class);
        $result = $spamChecker->checkSpam($comment);

        expect($result->isSpam)->toBeFalse();
        expect($result->metadata)->toHaveKey('reason', 'akismet_disabled');
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

describe('comment observer behavior', function (): void {
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
});

describe('comment component filtering', function (): void {
    it('filters out spam comments from display', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        // Create clean comment (will be set to clean by observer)
        $cleanComment = Comment::factory()->create([
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
