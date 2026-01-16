<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Jobs\CheckCommentForSpam;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Services\CommentSpamChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createModerator(): User
{
    return User::factory()->moderator()->create();
}

describe('Comment Spam Ribbon Updates', function (): void {
    it('dispatches comment-updated event when marking comment as spam', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('markCommentAsSpam', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: true);

        $comment->refresh();
        expect($comment->isSpam())->toBeTrue();
    });

    it('dispatches comment-updated event when marking comment as ham', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        // First mark as spam using the model method
        $comment->markAsSpamByModerator($moderator->id);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('markCommentAsHam', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: false);

        $comment->refresh();
        expect($comment->isSpamClean())->toBeTrue();
    });

    it('dispatches comment-updated event when soft deleting comment', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('softDeleteComment', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, deleted: true);

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('dispatches comment-updated event when restoring comment', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'deleted_at' => now(),
        ]);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('restoreComment', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, deleted: false);

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('updates comment status when spam check job completes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        // Test that the job updates the comment when Akismet is disabled
        Config::set('akismet.enabled', false);

        $job = new CheckCommentForSpam($comment, isRecheck: true);
        $job->handle(resolve(CommentSpamChecker::class));

        // Verify that the comment was marked as clean
        $comment->refresh();
        expect($comment->isSpamClean())->toBeTrue();
        expect($comment->spam_metadata['reason'])->toBe('akismet_disabled');
        expect($comment->spam_checked_at)->not->toBeNull();
    });

    it('polls for spam check completion and dispatches comment-updated event', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_checked_at' => now()->subMinute(), // Set initial timestamp
        ]);

        $this->actingAs($moderator);

        // Test the polling mechanism
        $component = Livewire::test('comment-component', ['commentable' => $mod]);

        // Start spam check which should set up polling state
        $component->call('checkCommentForSpam', $comment->id)
            ->assertDispatched('start-spam-check-polling', $comment->id);

        // Verify polling state is set
        expect($component->get('spamCheckStates')[$comment->id]['inProgress'])->toBeTrue();

        // Simulate job completion by updating the comment timestamp
        $comment->update(['spam_checked_at' => now()]);

        // Poll for status - should detect completion and dispatch event
        $component->call('pollSpamCheckStatus', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: false)
            ->assertDispatched('stop-spam-check-polling', $comment->id);

        // Verify polling state is cleared
        expect($component->get('spamCheckStates')[$comment->id]['inProgress'])->toBeFalse();
    });
});
