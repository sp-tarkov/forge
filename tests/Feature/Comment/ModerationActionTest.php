<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Jobs\CheckCommentForSpam;
use App\Livewire\Comment\Action;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('akismet.enabled', false);
});

// Helper function to create a moderator user
function createModerator(): User
{
    $moderatorRole = UserRole::factory()->moderator()->create();
    $moderator = User::factory()->create();
    $moderator->assignRole($moderatorRole);

    return $moderator;
}

// Helper function to create an administrator user
function createAdministrator(): User
{
    $adminRole = UserRole::factory()->administrator()->create();
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    return $admin;
}

describe('soft delete action', function (): void {
    it('allows moderators to soft delete non-spam comments', function (): void {
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

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('softDelete')
            ->assertDispatched('comment-updated.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh');

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents regular users from soft deleting comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('softDelete')
            ->assertForbidden();
    });

    it('prevents soft deleting already deleted comments', function (): void {
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

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('softDelete')
            ->assertForbidden();
    });
});

describe('hard delete action', function (): void {
    it('allows administrators to hard delete comment threads', function (): void {
        $admin = createAdministrator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $rootComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'root_id' => null,
        ]);

        $replyComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(Action::class, ['comment' => $rootComment])
            ->call('hardDeleteThread')
            ->assertDispatched('comment-deleted.'.$rootComment->id)
            ->assertDispatched('comment-moderation-refresh');

        expect(Comment::query()->find($rootComment->id))->toBeNull();
        expect(Comment::query()->find($replyComment->id))->toBeNull();
    });

    it('allows administrators to hard delete single reply comments', function (): void {
        $admin = createAdministrator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $rootComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'root_id' => null,
        ]);

        $replyComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(Action::class, ['comment' => $replyComment])
            ->call('hardDeleteThread');

        expect(Comment::query()->find($rootComment->id))->not->toBeNull();
        expect(Comment::query()->find($replyComment->id))->toBeNull();
    });

    it('prevents moderators from hard deleting comments', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('hardDeleteThread')
            ->assertForbidden();
    });

    it('prevents regular users from hard deleting comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('hardDeleteThread')
            ->assertForbidden();
    });

    it('allows administrators to hard delete soft-deleted comments', function (): void {
        $admin = createAdministrator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'deleted_at' => now(), // Soft-deleted comment
        ]);

        $this->actingAs($admin);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('hardDeleteThread')
            ->assertDispatched('comment-deleted.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh');

        expect(Comment::query()->find($comment->id))->toBeNull();
    });

    it('prevents moderators from hard deleting soft-deleted comments', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'deleted_at' => now(), // Soft-deleted comment
        ]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('hardDeleteThread')
            ->assertForbidden();
    });
});

describe('mark as spam action', function (): void {
    it('allows moderators to mark clean comments as spam', function (): void {
        Queue::fake();

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

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsSpam')
            ->assertDispatched('comment-updated.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh');

        $comment->refresh();
        expect($comment->isSpam())->toBeTrue();
        expect($comment->spam_metadata['manually_marked'])->toBeTrue();
        expect($comment->spam_metadata['marked_by'])->toBe($moderator->id);
    });

    it('prevents marking already spam comments as spam', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        $comment->save();
        $comment->update(['spam_status' => SpamStatus::SPAM]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsSpam')
            ->assertForbidden();
    });

    it('prevents regular users from marking as spam', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsSpam')
            ->assertForbidden();
    });
});

describe('mark as clean action', function (): void {
    it('allows moderators to mark spam comments as clean', function (): void {
        Queue::fake();

        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        $comment->save();
        $comment->update(['spam_status' => SpamStatus::SPAM]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsHam')
            ->assertDispatched('comment-updated.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh');

        $comment->refresh();
        expect($comment->isSpamClean())->toBeTrue();
        expect($comment->spam_metadata['manually_approved'])->toBeTrue();
    });

    it('prevents marking already clean comments as clean', function (): void {
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

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsHam')
            ->assertForbidden();
    });

    it('prevents regular users from marking as clean', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        $comment->save();
        $comment->update(['spam_status' => SpamStatus::SPAM]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('markAsHam')
            ->assertForbidden();
    });
});

describe('restore action', function (): void {
    it('allows moderators to restore soft deleted comments', function (): void {
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

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('restore')
            ->assertDispatched('comment-updated.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh');

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents restoring non-deleted comments', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('restore')
            ->assertForbidden();
    });

    it('prevents regular users from restoring comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'deleted_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('restore')
            ->assertForbidden();
    });
});

describe('check for spam action', function (): void {
    it('allows moderators to check comments for spam using Akismet', function (): void {
        Queue::fake();

        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_recheck_count' => 0,
        ]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('checkForSpam')
            ->assertSet('spamCheckInProgress', true)
            ->assertDispatched('start-spam-check-polling');

        Queue::assertPushed(CheckCommentForSpam::class, fn ($job): bool => $job->comment->id === $comment->id);
    });

    it('prevents checking comments that have exceeded max recheck attempts', function (): void {
        Config::set('comments.spam.max_recheck_attempts', 3);

        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_recheck_count' => 3,
        ]);

        $this->actingAs($moderator);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('checkForSpam')
            ->assertForbidden();
    });

    it('prevents regular users from checking for spam', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($user);

        Livewire::test(Action::class, ['comment' => $comment])
            ->call('checkForSpam')
            ->assertForbidden();
    });

    it('polls for spam check completion and updates UI', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_checked_at' => now()->subMinute(),
        ]);

        $this->actingAs($moderator);

        $component = Livewire::test(Action::class, ['comment' => $comment])
            ->set('spamCheckInProgress', true)
            ->set('spamCheckStartedAt', $comment->spam_checked_at->toISOString());

        // Simulate spam check completion by updating the comment
        $comment->update([
            'spam_status' => SpamStatus::SPAM,
            'spam_checked_at' => now(),
        ]);

        $component->call('pollSpamCheckStatus')
            ->assertSet('spamCheckInProgress', false)
            ->assertDispatched('comment-updated.'.$comment->id)
            ->assertDispatched('comment-moderation-refresh')
            ->assertDispatched('stop-spam-check-polling');
    });
});

describe('action visibility rules', function (): void {
    it('hides soft delete action for spam comments', function (): void {
        $admin = createAdministrator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $spamComment = Comment::factory()->make([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
        ]);
        $spamComment->save();
        $spamComment->update(['spam_status' => SpamStatus::SPAM]);

        $this->actingAs($admin);

        $component = Livewire::test(Action::class, ['comment' => $spamComment])
            ->call('loadMenu'); // Trigger lazy loading

        // Check that the soft delete menu item is not present in the menu
        $html = $component->html();
        expect($html)->toContain('Hard Delete Thread');
        expect($html)->not->toContain('icon:trailing="eye-slash">Soft Delete');
    });

    it('shows soft delete action for clean comments', function (): void {
        $admin = createAdministrator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $cleanComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(Action::class, ['comment' => $cleanComment])
            ->call('loadMenu'); // Trigger lazy loading

        // Check that the rendered component contains both actions for clean comments
        $component->assertSee('Soft Delete')
            ->assertSee('Hard Delete Thread');
    });

    it('respects policy rules for action visibility', function (): void {
        $admin = createAdministrator();
        $moderator = createModerator();
        $regularUser = User::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        // Administrator should be able to perform all actions
        $this->actingAs($admin);
        expect(auth()->user()->can('viewActions', $comment))->toBeTrue();
        expect(auth()->user()->can('markAsSpam', $comment))->toBeTrue();
        expect(auth()->user()->can('softDelete', $comment))->toBeTrue();
        expect(auth()->user()->can('hardDelete', $comment))->toBeTrue();

        // Moderator should be able to perform most actions but NOT hard delete
        $this->actingAs($moderator);
        expect(auth()->user()->can('viewActions', $comment))->toBeTrue();
        expect(auth()->user()->can('markAsSpam', $comment))->toBeTrue();
        expect(auth()->user()->can('softDelete', $comment))->toBeTrue();
        expect(auth()->user()->can('hardDelete', $comment))->toBeFalse();

        // Regular user should not be able to perform moderation actions
        $this->actingAs($regularUser);
        expect(auth()->user()->can('viewActions', $comment))->toBeFalse();
        expect(auth()->user()->can('markAsSpam', $comment))->toBeFalse();
        expect(auth()->user()->can('softDelete', $comment))->toBeFalse();
        expect(auth()->user()->can('hardDelete', $comment))->toBeFalse();
    });
});
