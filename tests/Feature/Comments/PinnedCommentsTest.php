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

describe('Pin Authorization', function () {
    it('allows mod owners to pin comments', function () {
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

    it('allows mod authors to pin comments', function () {
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

    it('allows moderators to pin comments', function () {
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

    it('allows administrators to pin comments', function () {
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

    it('prevents regular users from pinning comments', function () {
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

    it('prevents pinning reply comments', function () {
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

describe('Pin Ordering', function () {
    it('displays pinned comments first', function () {
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

    it('orders multiple pinned comments by pin time', function () {
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

describe('Pin Functionality', function () {
    it('allows unpinning comments', function () {
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
});

describe('Pin Action Visibility', function () {
    it('shows owner pin actions correctly based on user role', function () {
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
