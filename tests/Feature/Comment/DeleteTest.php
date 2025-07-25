<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Helper function to create a published mod.
 */
function createPublishedMod(): Mod
{
    $user = User::factory()->create();
    SptVersion::factory()->create(['version' => '1.0.0']);

    $mod = Mod::factory()->create(['published_at' => now()->subHour()]);
    $mod->owner()->associate($user);
    $mod->save();

    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    return $mod;
}

describe('hard deletion', function (): void {
    it('hard deletes comments within 5 minutes with no children', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'created_at' => now()->subMinutes(3), // 3 minutes old
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $comment)
            ->assertSuccessful();

        // Comment should be hard deleted
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    });

    it('hard deletes comments at exactly 5 minutes with no children', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'created_at' => now()->subMinutes(5)->addSeconds(30), // 4 minutes 30 seconds ago
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $comment)
            ->assertSuccessful();

        // Comment should be hard deleted (still within 5 minutes)
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    });
});

describe('soft deletion', function (): void {
    it('soft deletes comments older than 5 minutes', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'created_at' => now()->subMinutes(10), // 10 minutes old
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $comment)
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue()
            ->and($comment->deleted_at)->not->toBeNull();

        // Comment should still exist in the database
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    });

    it('soft deletes comments within 5 minutes that have children', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Parent comment',
            'created_at' => now()->subMinutes(3), // 3 minutes old
        ]);

        $childComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Child comment',
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $parentComment)
            ->assertSuccessful();

        // Parent should be soft-deleted because it has children
        $parentComment->refresh();
        expect($parentComment->isDeleted())->toBeTrue()
            ->and($parentComment->deleted_at)->not->toBeNull();

        // Parent should still exist in the database
        $this->assertDatabaseHas('comments', ['id' => $parentComment->id]);
    });
});

describe('deletion permissions', function (): void {
    it('prevents users from deleting other users comments', function (): void {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $author->id,
            'body' => 'This is my comment',
        ]);

        $this->actingAs($otherUser);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $comment)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse()
            ->and($comment->deleted_at)->toBeNull();
    });

    it('prevents deleting already deleted comments', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'deleted_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $comment)
            ->assertForbidden();
    });
});

describe('deleted comment display', function (): void {
    it('shows deleted placeholder instead of comment content', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'deleted_at' => now()->subMinutes(5),
        ]);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('[deleted at')
            ->assertDontSee('This is my comment');
    });

    it('hides action buttons for deleted comments', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'This is my comment',
            'deleted_at' => now(),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(CommentComponent::class, ['commentable' => $mod]);

        // Check that the specific action buttons are not present for deleted comments
        $response->assertDontSee('wire:click="toggleEditForm('.$comment->id.')"', false)
            ->assertDontSee('wire:click="deleteComment('.$comment->id.')"', false)
            ->assertDontSee('wire:click="toggleReplyForm('.$comment->id.')"', false);
    });

    it('shows delete button only to comment author', function (): void {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $author->id,
            'body' => 'This is my comment',
        ]);

        // Author should see delete button
        $this->actingAs($author);
        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('Delete');

        // Other user should not see delete button
        $this->actingAs($otherUser);
        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertDontSee('Delete');
    });
});

describe('comment hierarchy', function (): void {
    it('preserves child comments when parent is deleted', function (): void {
        $user = User::factory()->create();
        $mod = createPublishedMod();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Parent comment',
        ]);

        $childComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'body' => 'Child comment',
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
        ]);

        $this->actingAs($user);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->call('deleteComment', $parentComment)
            ->assertSuccessful();

        $parentComment->refresh();
        $childComment->refresh();

        expect($parentComment->isDeleted())->toBeTrue();
        expect($childComment->isDeleted())->toBeFalse();
        expect($childComment->body)->toBe('Child comment');
    });
});

describe('moderator and admin visibility', function (): void {
    it('shows deleted comment text to moderators in red', function (): void {
        $moderatorRole = UserRole::factory()->create(['name' => 'moderator']);
        $moderator = User::factory()->create(['user_role_id' => $moderatorRole->id]);
        $commentAuthor = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commentAuthor->id,
            'body' => 'This comment will be deleted',
            'deleted_at' => now(),
        ]);

        $this->actingAs($moderator);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('Comment was deleted on')
            ->assertSee('This comment will be deleted')
            ->assertSee('text-red-500', false); // Check for red text class
    });

    it('shows deleted comment text to administrators in red', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'administrator']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $commentAuthor = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commentAuthor->id,
            'body' => 'This admin-viewable deleted comment',
            'deleted_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('Comment was deleted on')
            ->assertSee('This admin-viewable deleted comment')
            ->assertSee('text-red-500', false); // Check for red text class
    });

    it('hides deleted comment text from regular users', function (): void {
        $regularUser = User::factory()->create();
        $commentAuthor = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commentAuthor->id,
            'body' => 'This comment should not be visible to regular users',
            'deleted_at' => now(),
        ]);

        $this->actingAs($regularUser);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('[deleted at')
            ->assertDontSee('This comment should not be visible to regular users')
            ->assertDontSee('Comment was deleted on');
    });

    it('hides deleted comment text from guests', function (): void {
        $commentAuthor = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commentAuthor->id,
            'body' => 'This comment should not be visible to guests',
            'deleted_at' => now(),
        ]);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('[deleted at')
            ->assertDontSee('This comment should not be visible to guests')
            ->assertDontSee('Comment was deleted on');
    });
});
