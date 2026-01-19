<?php

declare(strict_types=1);

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
    SptVersion::query()->firstOrCreate(['version' => '1.0.0'], SptVersion::factory()->make(['version' => '1.0.0'])->toArray());

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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        $response = Livewire::test('comment-component', ['commentable' => $mod]);

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
        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('Remove');

        // Other user should not see delete button
        $this->actingAs($otherUser);
        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertDontSee('Remove');
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

        Livewire::test('comment-component', ['commentable' => $mod])
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
        $moderator = User::factory()->moderator()->create();
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('Comment was deleted on')
            ->assertSee('This comment will be deleted')
            ->assertSee('class="deleted"', false); // Check for deleted class that applies red styling
    });

    it('shows deleted comment text to administrators in red', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('Comment was deleted on')
            ->assertSee('This admin-viewable deleted comment')
            ->assertSee('class="deleted"', false); // Check for deleted class that applies red styling
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

        Livewire::test('comment-component', ['commentable' => $mod])
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('[deleted at')
            ->assertDontSee('This comment should not be visible to guests')
            ->assertDontSee('Comment was deleted on');
    });
});

describe('mod owner soft deletion', function (): void {
    it('allows mod owners to soft delete comments on their mods', function (): void {
        $modOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This is a comment to delete',
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertSet('showModOwnerSoftDeleteModal', true)
            ->assertSet('modOwnerSoftDeletingCommentId', $comment->id)
            ->call('modOwnerSoftDeleteComment')
            ->assertSet('showModOwnerSoftDeleteModal', false)
            ->assertSet('modOwnerSoftDeletingCommentId', null);

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue()
            ->and($comment->deleted_at)->not->toBeNull();
    });

    it('allows mod authors to soft delete comments on mods they co-author', function (): void {
        $modAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->additionalAuthors()->attach($modAuthor);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This is a comment to delete',
        ]);

        $this->actingAs($modAuthor);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertSet('showModOwnerSoftDeleteModal', true)
            ->call('modOwnerSoftDeleteComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents mod owners from soft deleting comments on other mods', function (): void {
        $modOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $mod1 = createPublishedMod();
        $mod1->owner_id = $modOwner->id;
        $mod1->save();

        $mod2 = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod2->id,
            'user_id' => $commenter->id,
            'body' => 'This comment is on a different mod',
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod2])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents regular users from soft deleting comments as mod owners', function (): void {
        $regularUser = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This comment should not be deletable',
        ]);

        $this->actingAs($regularUser);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents moderators who are not mod owners from using mod owner soft delete action', function (): void {
        $moderator = User::factory()->moderator()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'Moderators who are not owners cannot use this',
        ]);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents admins who are not mod owners from using mod owner soft delete action', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $commenter = User::factory()->create();
        $mod = createPublishedMod();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'Admins who are not owners cannot use this',
        ]);

        $this->actingAs($admin);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('allows administrators who are mod owners to use mod owner soft delete', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $adminModOwner = User::factory()->create(['user_role_id' => $adminRole->id]);
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $adminModOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'Admin mod owners can delete this',
        ]);

        $this->actingAs($adminModOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertSet('showModOwnerSoftDeleteModal', true)
            ->call('modOwnerSoftDeleteComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents soft deleting already deleted comments via mod owner action', function (): void {
        $modOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'Already deleted comment',
            'deleted_at' => now()->subHour(),
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();
    });

    it('allows profile owners to soft delete comments on their profile', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
            'body' => 'Comment on user profile',
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertSet('showModOwnerSoftDeleteModal', true)
            ->call('modOwnerSoftDeleteComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents users from soft deleting comments on other users profiles', function (): void {
        $profileOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
            'body' => 'Comment on someone elses profile',
        ]);

        $this->actingAs($otherUser);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('allows administrator profile owners to soft delete comments on their profile', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $adminProfileOwner = User::factory()->create(['user_role_id' => $adminRole->id]);
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $adminProfileOwner->id,
            'user_id' => $commenter->id,
            'body' => 'Comment on admin profile',
        ]);

        $this->actingAs($adminProfileOwner);

        Livewire::test('comment-component', ['commentable' => $adminProfileOwner])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertSet('showModOwnerSoftDeleteModal', true)
            ->call('modOwnerSoftDeleteComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents mod owners from soft deleting comments made by administrators', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $modOwner = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $admin->id,
            'body' => 'Administrative comment',
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents mod owners from soft deleting comments made by moderators', function (): void {
        $moderator = User::factory()->moderator()->create();
        $modOwner = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $moderator->id,
            'body' => 'Moderator comment',
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents profile owners from soft deleting comments made by administrators', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $profileOwner = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $admin->id,
            'body' => 'Administrative comment on profile',
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents profile owners from soft deleting comments made by moderators', function (): void {
        $moderator = User::factory()->moderator()->create();
        $profileOwner = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $moderator->id,
            'body' => 'Moderator comment on profile',
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerSoftDeleteComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });
});

describe('mod owner restore', function (): void {
    it('allows mod owners to restore deleted comments on their mods', function (): void {
        $modOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This is a deleted comment',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertSet('showModOwnerRestoreModal', true)
            ->assertSet('modOwnerRestoringCommentId', $comment->id)
            ->call('modOwnerRestoreComment')
            ->assertSet('showModOwnerRestoreModal', false)
            ->assertSet('modOwnerRestoringCommentId', null);

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse()
            ->and($comment->deleted_at)->toBeNull();
    });

    it('allows mod authors to restore deleted comments on mods they co-author', function (): void {
        $modAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->additionalAuthors()->attach($modAuthor);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This is a deleted comment',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($modAuthor);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->call('modOwnerRestoreComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('allows profile owners to restore deleted comments on their profiles', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
            'body' => 'This is a deleted profile comment',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertSet('showModOwnerRestoreModal', true)
            ->call('modOwnerRestoreComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents regular users from restoring comments they do not own', function (): void {
        $modOwner = User::factory()->create();
        $regularUser = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This comment should not be restorable',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($regularUser);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents restoring non-deleted comments', function (): void {
        $modOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $commenter->id,
            'body' => 'This comment is not deleted',
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();
    });

    it('allows administrator profile owners to restore comments on their profiles', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $adminProfileOwner = User::factory()->create(['user_role_id' => $adminRole->id]);
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $adminProfileOwner->id,
            'user_id' => $commenter->id,
            'body' => 'Deleted comment on admin profile',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($adminProfileOwner);

        Livewire::test('comment-component', ['commentable' => $adminProfileOwner])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->call('modOwnerRestoreComment')
            ->assertSuccessful();

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('prevents mod owners from restoring deleted comments made by administrators', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $modOwner = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $admin->id,
            'body' => 'Administrative comment',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents mod owners from restoring deleted comments made by moderators', function (): void {
        $moderator = User::factory()->moderator()->create();
        $modOwner = User::factory()->create();
        $mod = createPublishedMod();
        $mod->owner_id = $modOwner->id;
        $mod->save();

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $moderator->id,
            'body' => 'Moderator comment',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($modOwner);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents profile owners from restoring deleted comments made by administrators', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);
        $profileOwner = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $admin->id,
            'body' => 'Administrative comment on profile',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('prevents profile owners from restoring deleted comments made by moderators', function (): void {
        $moderator = User::factory()->moderator()->create();
        $profileOwner = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $moderator->id,
            'body' => 'Moderator comment on profile',
            'deleted_at' => now()->subMinute(),
        ]);

        $this->actingAs($profileOwner);

        Livewire::test('comment-component', ['commentable' => $profileOwner])
            ->call('confirmModOwnerRestoreComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });
});
