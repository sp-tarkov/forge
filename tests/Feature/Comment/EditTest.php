<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
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
        $comment->versions()->create([
            'body' => 'Original comment.',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');

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
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', "Trying to edit someone else's comment")
            ->call('updateComment', $comment->id)
            ->assertForbidden();

        // Verify the comment was not changed
        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->not->toBe("Trying to edit someone else's comment");
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
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Edited content')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
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
        $comment->versions()->create([
            'body' => 'Some content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod]);

        // Check for edited indicator - the version history dropdown shows "edited"
        $html = $component->html();
        expect($html)->toContain('edited');
    });

    it('should refresh the comment listing with edited text after successful edit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $originalText = 'This is the original comment text.';
        $editedText = 'This is the edited comment text that should appear in the listing.';

        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => $originalText,
            'version_number' => 1,
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSee($originalText)
            ->assertDontSee($editedText);

        $component->set('formStates.edit-'.$comment->id.'.body', $editedText)
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe($editedText)
            ->and($comment->edited_at)->not->toBeNull();

        $component->assertSee($editedText)
            ->assertDontSee($originalText);
    });
});

describe('moderator permissions', function (): void {
    it('should not allow moderators to edit other users comments', function (): void {
        $moderator = User::factory()->moderator()->create();

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($moderator)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Moderator trying to edit')
            ->call('updateComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->not->toBe('Moderator trying to edit');
    });

    it('should allow moderators to edit their own comments', function (): void {
        $moderator = User::factory()->moderator()->create();

        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $moderator->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($moderator)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Moderator editing own comment')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Moderator editing own comment');
    });

    it('should allow moderators to edit their own old comments', function (): void {
        $moderator = User::factory()->moderator()->create();

        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $moderator->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(7), // Week old
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now()->subDays(7),
        ]);

        // Moderator should be able to edit their own old comments (no time limit)
        Livewire::actingAs($moderator)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Moderator editing old own comment')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Moderator editing old own comment');
    });
});

describe('administrator permissions', function (): void {
    it('should not allow administrators to edit other users comments', function (): void {
        $admin = User::factory()->admin()->create();

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(30), // Old comment
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now()->subDays(30),
        ]);

        Livewire::actingAs($admin)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Admin trying to edit')
            ->call('updateComment', $comment->id)
            ->assertForbidden();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->not->toBe('Admin trying to edit');
    });

    it('should allow administrators to edit their own comments', function (): void {
        $admin = User::factory()->admin()->create();

        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $admin->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Admin editing own comment')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Admin editing own comment');
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
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        // Try to edit it from mod2's comment manager
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod2])
            ->set('formStates.edit-'.$comment->id.'.body', 'Cross-mod edit attempt')
            ->call('updateComment', $comment->id)
            ->assertForbidden();
    });
});

describe('regular user restrictions', function (): void {
    it("should not allow a regular user to update another user's comment", function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'This is an updated comment.')
            ->call('updateComment', $comment->id)
            ->assertForbidden();
    });
});

describe('concurrent editing', function (): void {
    it('should handle concurrent edit attempts on same comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        // User starts editing their own comment
        $component1 = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'First edit');

        // User submits first edit successfully
        $component1->call('updateComment', $comment->id)->assertHasNoErrors();

        // Verify first edit succeeded
        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('First edit');

        // User makes another edit on the same comment
        $component2 = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Second edit');

        // Second edit should also succeed (no time limit anymore)
        $component2->call('updateComment', $comment->id)->assertHasNoErrors();

        // The second edit should win
        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Second edit');
    });

    it('should allow editing old comments multiple times', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now()->subDays(7), // Week old comment
        ]);
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now()->subDays(7),
        ]);

        // First edit on old comment should succeed
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'First edit on old comment')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        // Second edit should also succeed
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Second edit on old comment')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Second edit on old comment')
            ->and($comment->versions()->count())->toBe(3);
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
        $comment->versions()->create([
            'body' => 'Original content',
            'version_number' => 1,
            'created_at' => now()->subDays(1),
        ]);

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod]);

        // Try to force to show an edit form for a comment user can't edit
        $component->set('formStates.edit-'.$comment->id.'.body', 'Forced edit attempt')
            ->call('updateComment', $comment->id)
            ->assertForbidden();
    });
});

describe('body trimming during edit', function (): void {
    it('should trim whitespace when editing a comment through Livewire', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Original comment',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', '  Edited comment with spaces  ')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Edited comment with spaces');
    });

    it('should trim tabs and newlines when editing a comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'created_at' => now(),
        ]);
        $comment->versions()->create([
            'body' => 'Original comment',
            'version_number' => 1,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', "\t\n  Edited with tabs and newlines\n\t  ")
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        $comment->unsetRelation('latestVersion');
        expect($comment->body)->toBe('Edited with tabs and newlines');
    });
});
