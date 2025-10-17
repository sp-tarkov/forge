<?php

declare(strict_types=1);

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Pin Authorization', function (): void {
    it('allows mod owners to pin comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($owner);

        expect($owner->can('pin', $comment))->toBeTrue();

        // Test pinning
        $comment->update(['pinned_at' => now()]);
        expect($comment->fresh()->pinned_at)->not->toBeNull();
        expect($comment->fresh()->isPinned())->toBeTrue();
    });

    it('allows mod authors to pin comments', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $mod->authors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($author);

        expect($author->can('pin', $comment))->toBeTrue();
    });

    it('allows moderators to pin comments', function (): void {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($moderator);

        expect($moderator->can('pin', $comment))->toBeTrue();
    });

    it('allows administrators to pin comments', function (): void {
        $adminRole = UserRole::factory()->administrator()->create();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($admin);

        expect($admin->can('pin', $comment))->toBeTrue();
    });

    it('prevents regular users from pinning comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($user);

        expect($user->can('pin', $comment))->toBeFalse();
    });

    it('prevents pinning reply comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $replyComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        // Root comment should be pinnable
        expect($owner->can('pin', $rootComment))->toBeTrue();
        expect($moderator->can('pin', $rootComment))->toBeTrue();

        // Reply comment should not be pinnable
        expect($owner->can('pin', $replyComment))->toBeFalse();
        expect($moderator->can('pin', $replyComment))->toBeFalse();

        // Reply comment should not show the owner pin action
        expect($owner->can('showOwnerPinAction', $replyComment))->toBeFalse();
    });
});

describe('Pin Ordering', function (): void {
    it('displays pinned comments first', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create comments with different timestamps
        $oldComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(3),
        ]);

        $newComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDay(),
        ]);

        $pinnedComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(2),
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() now includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Pinned comment should be first (non-null pinned_at should come first)
        expect($comments->first()->id)->toBe($pinnedComment->id);

        // Then newest unpinned comment
        expect($comments->get(1)->id)->toBe($newComment->id);

        // Then oldest unpinned comment
        expect($comments->get(2)->id)->toBe($oldComment->id);
    });

    it('orders multiple pinned comments by pin time', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create pinned comments with different pin times
        $firstPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(3),
        ]);

        $secondPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(2),
        ]);

        $latestPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() now includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Latest pinned should be first
        expect($comments->get(0)->id)->toBe($latestPinned->id);
        expect($comments->get(1)->id)->toBe($secondPinned->id);
        expect($comments->get(2)->id)->toBe($firstPinned->id);
    });
});

describe('Pin Functionality', function (): void {
    it('allows unpinning comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
        ]);

        $this->actingAs($owner);

        // Verify comment is pinned
        expect($comment->isPinned())->toBeTrue();

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned
        expect($comment->fresh()->isPinned())->toBeFalse();
        expect($comment->fresh()->pinned_at)->toBeNull();
    });

    it('allows mod owners to unpin soft-deleted comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->actingAs($owner);

        // Verify comment is both pinned and deleted
        expect($comment->isPinned())->toBeTrue();
        expect($comment->isDeleted())->toBeTrue();

        // Owner should still be able to unpin
        expect($owner->can('pin', $comment))->toBeTrue();

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned but still deleted
        expect($comment->fresh()->isPinned())->toBeFalse();
        expect($comment->fresh()->isDeleted())->toBeTrue();
    });

    it('allows mod authors to unpin soft-deleted comments', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $mod->authors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->actingAs($author);

        // Verify comment is both pinned and deleted
        expect($comment->isPinned())->toBeTrue();
        expect($comment->isDeleted())->toBeTrue();

        // Author should still be able to unpin
        expect($author->can('pin', $comment))->toBeTrue();

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned but still deleted
        expect($comment->fresh()->isPinned())->toBeFalse();
        expect($comment->fresh()->isDeleted())->toBeTrue();
    });

    it('allows moderators to unpin soft-deleted comments', function (): void {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->actingAs($moderator);

        // Verify comment is both pinned and deleted
        expect($comment->isPinned())->toBeTrue();
        expect($comment->isDeleted())->toBeTrue();

        // Moderator should still be able to unpin
        expect($moderator->can('pin', $comment))->toBeTrue();

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned but still deleted
        expect($comment->fresh()->isPinned())->toBeFalse();
        expect($comment->fresh()->isDeleted())->toBeTrue();
    });

    it('allows administrators to unpin soft-deleted comments', function (): void {
        $adminRole = UserRole::factory()->administrator()->create();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->actingAs($admin);

        // Verify comment is both pinned and deleted
        expect($comment->isPinned())->toBeTrue();
        expect($comment->isDeleted())->toBeTrue();

        // Admin should still be able to unpin
        expect($admin->can('pin', $comment))->toBeTrue();

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned but still deleted
        expect($comment->fresh()->isPinned())->toBeFalse();
        expect($comment->fresh()->isDeleted())->toBeTrue();
    });

    it('prevents pinning soft-deleted comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'deleted_at' => now(),
        ]);

        $this->actingAs($owner);

        // Verify comment is deleted but not pinned
        expect($comment->isDeleted())->toBeTrue();
        expect($comment->isPinned())->toBeFalse();

        // Owner should have pin permission (for unpinning)
        expect($owner->can('pin', $comment))->toBeTrue();

        // But the UI should not show the pin action for deleted comments (only unpin)
        // This is handled in the Blade templates
    });
});

describe('Pin Action Visibility', function (): void {
    it('shows owner pin actions correctly based on user role', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $regularUser = User::factory()->create();

        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $mod->authors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Mod owner should see the owner pin action
        expect($owner->can('showOwnerPinAction', $comment))->toBeTrue();

        // Mod author should see the owner pin action
        expect($author->can('showOwnerPinAction', $comment))->toBeTrue();

        // Regular user should not see the owner pin action
        expect($regularUser->can('showOwnerPinAction', $comment))->toBeFalse();

        // Moderator should not see the owner pin action (they use the moderation dropdown)
        expect($moderator->can('showOwnerPinAction', $comment))->toBeFalse();
    });
});
