<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('basic editing', function (): void {
    it('should allow a user to update their own comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();

        $comment->refresh();

        $this->assertEquals('This is an updated comment.', $comment->body);
        $this->assertNotNull($comment->edited_at);
    });

    it('should not allow users to edit comments they do not own', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', "Trying to edit someone else's comment")
            ->call('updateComment', $comment)
            ->assertForbidden();

        // Verify the comment was not changed
        $comment->refresh();
        expect($comment->body)->not->toBe("Trying to edit someone else's comment");
    });
});

describe('time-based restrictions', function (): void {
    it('should not allow a user to update a comment that is older than 5 minutes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subMinutes(6),
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });

    it('should not allow editing comments older than 5 minutes even if user owns it', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subMinutes(6),
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Too late to edit')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });
});

describe('edit tracking', function (): void {
    it('should track edited_at timestamp when comment is updated', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
            'edited_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Edited content')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->edited_at)->not->toBeNull()
            ->and($comment->body)->toBe('Edited content');
    });

    it('should show an edited indicator when a comment has been edited', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'edited_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->assertSeeHtml('<span class="text-gray-500 dark:text-gray-400" title="'.$comment->edited_at->format('Y-m-d H:i:s').'">*</span>');
    });
});

describe('moderator permissions', function (): void {
    it('should allow moderators to edit any comment regardless of ownership', function (): void {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);

        Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Moderator edit')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Moderator edit');
    });

    it('should allow a moderator to update any comment', function (): void {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id, 'commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

        Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();
    });

    it('should properly check moderator permissions for special actions', function (): void {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Create an old comment
        $oldComment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(7), // Week old
        ]);

        // Moderator should be able to edit old comments
        Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$oldComment->id.'.body', 'Moderator edit on old comment')
            ->call('updateComment', $oldComment)
            ->assertHasNoErrors();

        $oldComment->refresh();
        expect($oldComment->body)->toBe('Moderator edit on old comment');
    });
});

describe('administrator permissions', function (): void {
    it('should allow administrators to edit any comment regardless of ownership', function (): void {
        $adminRole = UserRole::factory()->administrator()->create();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(30), // Old comment
        ]);

        Livewire::actingAs($admin)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Admin edit')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Admin edit');
    });
});

describe('cross-mod security', function (): void {
    it('should not allow editing comments from a different mod', function (): void {
        $user = User::factory()->create();
        $mod1 = Mod::factory()->create();
        $mod2 = Mod::factory()->create();

        // Create a comment on mod1
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod1->id,
            'commentable_type' => $mod1::class,
        ]);

        // Try to edit it from mod2's comment manager
        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod2])
            ->set('formStates.edit-'.$comment->id.'.body', 'Cross-mod edit attempt')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });
});

describe('regular user restrictions', function (): void {
    it("should not allow a regular user to update another user's comment", function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id, 'commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });

    it('should not allow regular users to bypass moderator-only actions', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();

        // Create an old comment by another user
        $oldComment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(1),
        ]);

        // Regular user shouldn't be able to edit old comments even if they try
        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$oldComment->id.'.body', 'Attempting privilege escalation')
            ->call('updateComment', $oldComment)
            ->assertForbidden();
    });
});

describe('concurrent editing', function (): void {
    it('should handle concurrent edit attempts gracefully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);

        // The first edit succeeds
        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'First edit')
            ->call('updateComment', $comment)
            ->assertHasNoErrors();

        // Simulate time passing (comment is now older than 5 minutes)
        $comment->update(['created_at' => now()->subMinutes(6)]);

        // The second edit should fail due to time constraint
        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Second edit')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });

    it('should handle concurrent edit attempts on same comment', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $user2->assignRole($moderatorRole); // Make user2 a moderator

        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user1->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'body' => 'Original content',
            'created_at' => now(),
        ]);

        // User1 starts editing
        $component1 = Livewire::actingAs($user1)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'User1 edit');

        // Moderator also starts editing
        $component2 = Livewire::actingAs($user2)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Moderator edit');

        // Both submit their edits
        $component1->call('updateComment', $comment)->assertHasNoErrors();
        $component2->call('updateComment', $comment)->assertHasNoErrors();

        // The last edit (moderator's) should win
        $comment->refresh();
        expect($comment->body)->toBe('Moderator edit');
    });
});

describe('security', function (): void {
    it('should not allow manipulating component state to show hidden forms', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();

        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(1), // Old comment
        ]);

        $component = Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod]);

        // Try to force to show an edit form for a comment user can't edit
        $component->set('formStates.edit-'.$comment->id.'.body', 'Forced edit attempt')
            ->call('updateComment', $comment)
            ->assertForbidden();
    });
});
