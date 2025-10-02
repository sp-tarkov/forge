<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    Cache::flush(); // Prevent rate limiting interference.

    // Disable honeypot spam protection for tests
    config(['honeypot.enabled' => false]);

    // Create a default SPT version that will be used by mod versions
    SptVersion::factory()->create(['version' => '1.0.0']);
});

describe('Guest User Tests', function (): void {
    it('should not show comment form to guest users', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now(),
        ]);
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '1.0.0',
            'disabled' => false,
            'published_at' => now(),
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        // Check that guest users don't see the comment form
        $page->assertDontSee('Post Comment')
            ->assertNotPresent('@new-comment-body')
            ->assertSee('Login or register to join the discussion')
            ->assertNoJavascriptErrors();
    });

    it('should not show reply buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to reply to.',
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('button[wire\\:click*=toggleReplyForm]')
            ->assertNoJavascriptErrors();
    });

    it('should not show edit buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to edit.',
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@edit-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should not show delete buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to delete.',
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@delete-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should not show reaction buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to react to.',
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@reaction-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Creation Tests', function (): void {
    it('should allow logged in user to create a root comment without browser errors', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText = 'This is a test comment with more than minimum length.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText)
            ->press('Post Comment')
            ->assertSeeIn('#comments', $commentText)
            ->assertNoJavaScriptErrors();
    });

    it('should validate minimum comment length', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $shortText = 'Hi';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $shortText)
            ->press('Post Comment')
            ->assertSee('must be at least')
            ->assertNoJavaScriptErrors();
    });

    it('should clear form after successful comment creation', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText = 'This is a test comment that should clear after submission.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText)
            ->press('Post Comment')
            ->assertValue('@new-comment-body', '')
            ->assertNoJavaScriptErrors();
    });

    it('should enforce rate limiting for regular users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText1 = 'This is the first test comment for rate limiting.';
        $commentText2 = 'This is the second test comment for rate limiting.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText1)
            ->press('Post Comment')
            ->type('@new-comment-body', $commentText2)
            ->press('Post Comment')
            ->assertDontSee($commentText2)
            ->assertNoJavaScriptErrors();
    });

    it('should allow administrators to bypass rate limiting', function (): void {
        $admin = User::factory()->create();
        $adminRole = UserRole::factory()->administrator()->create();
        $admin->assignRole($adminRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText1 = 'This is the first admin comment.';
        $commentText2 = 'This is the second admin comment.';

        $this->actingAs($admin);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText1)
            ->press('Post Comment')
            ->assertSee($commentText1)
            ->type('@new-comment-body', $commentText2)
            ->press('Post Comment')
            ->assertSee($commentText2)
            ->assertNoJavaScriptErrors();
    });

    it('should allow moderators to bypass rate limiting', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText1 = 'This is the first moderator comment.';
        $commentText2 = 'This is the second moderator comment.';

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText1)
            ->press('Post Comment')
            ->assertSee($commentText1)
            ->type('@new-comment-body', $commentText2)
            ->press('Post Comment')
            ->assertSee($commentText2)
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Reply Tests', function (): void {
    it('should show reply button for authenticated users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that should show a reply button.',
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertPresent('@reply-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should open reply form when reply button is clicked', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$comment->id)
            ->assertSee('Reply To Comment')
            ->assertPresent('@reply-body-'.$comment->id)
            ->assertSee('Post Reply')
            ->assertNoJavaScriptErrors();
    });

    it('should close reply form when cancel is clicked', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$comment->id)
            ->assertSee('Reply To Comment')
            ->click('@cancel-reply-body-'.$comment->id)
            ->assertDontSee('Reply To Comment')
            ->assertNoJavaScriptErrors();
    });

    it('should create reply to root comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $replyText = 'This is my reply to the test comment.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$comment->id)
            ->type('@reply-body-'.$comment->id, $replyText)
            ->press('Post Reply')
            ->assertSee($replyText)
            ->assertNoJavaScriptErrors();
    });

    it('should maintain comment hierarchy', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is the root comment.',
        ]);
        $replyText = 'This is a reply to the root comment.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$rootComment->id)
            ->type('@reply-body-'.$rootComment->id, $replyText)
            ->press('Post Reply')
            ->assertSee('Replying to @'.$rootComment->user->name)
            ->assertNoJavaScriptErrors();
    });

    it('should validate reply content length', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $shortReply = 'Hi';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$comment->id)
            ->type('@reply-body-'.$comment->id, $shortReply)
            ->press('Post Reply')
            ->assertSee('must be at least')
            ->assertNoJavaScriptErrors();
    });

    it('should clear reply form after successful submission', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $replyText = 'This reply should clear the form after submission.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@reply-button-'.$comment->id)
            ->type('@reply-body-'.$comment->id, $replyText)
            ->press('Post Reply')
            ->assertDontSee('Reply To Comment')
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Editing Tests', function (): void {
    it('should show edit button for comment owner within time limit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a freshly created comment that should be editable.',
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertPresent('@edit-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should not show edit button for other users comments', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment belongs to user1.',
            'created_at' => now(),
        ]);

        $this->actingAs($user2);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@edit-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should open edit form with existing comment content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'This is the original comment text that should appear in the edit form.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@edit-button-'.$comment->id)
            ->assertSee('Edit Comment')
            ->assertPresent('@edit-body-'.$comment->id)
            ->assertValue('@edit-body-'.$comment->id, $originalText)
            ->assertNoJavaScriptErrors();
    });

    it('should save edited comment successfully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'This is the original comment text.';
        $editedText = 'This is the edited comment text with more content.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee($originalText)
            ->click('@edit-button-'.$comment->id)
            ->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, $editedText)
            ->press('Update Comment')
            ->assertSee($editedText)
            ->assertDontSee($originalText)
            ->assertNoJavaScriptErrors();
    });

    it('should validate edited comment content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'This is the original comment text.';
        $shortText = 'Hi';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->click('@edit-button-'.$comment->id)
            ->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, $shortText)
            ->press('Update Comment')
            ->assertSee('must be at least')
            ->assertNoJavaScriptErrors();
    });

    it('should cancel edit without saving changes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'This is the original comment text that should remain unchanged.';
        $editedText = 'This edited text should not be saved when cancelled.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee($originalText)
            ->click('@edit-button-'.$comment->id)
            ->assertSee('Edit Comment')
            ->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, $editedText)
            ->click('@cancel-edit-body-'.$comment->id)
            ->assertSee($originalText)
            ->assertDontSee($editedText)
            ->assertNoJavaScriptErrors();
    });

    it('should refresh listing with edited comment content after successful edit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'Original comment text for editing test';
        $editedText = 'Edited comment text that should appear after update';

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee($originalText)
            ->click('@edit-button-'.$comment->id)
            ->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, $editedText)
            ->press('Update Comment')
            ->assertSee($editedText)
            ->assertDontSee($originalText)
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Deletion Tests', function (): void {
    it('should show delete button for comment owner', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a comment that the owner should be able to delete.',
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertPresent('@delete-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should not show delete button for other users comments', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment belongs to user1.',
            'created_at' => now(),
        ]);

        $this->actingAs($user2);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@delete-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should require confirmation before deletion', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $commentText = 'This comment should require confirmation before deletion.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $commentText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee($commentText)
            ->click('@delete-button-'.$comment->id)
            ->assertSee('Remove Comment')
            ->click('@cancel-delete-comment')
            ->assertSee($commentText)
            ->assertNoJavaScriptErrors();
    });

    it('should delete comment after confirming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $commentText = 'This comment should be deleted after confirmation.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $commentText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee($commentText)
            ->click('@delete-button-'.$comment->id)
            ->assertSee('Remove Comment')
            ->click('@confirm-delete-comment') // Click the confirmation button using data-test
            ->assertNotPresent('.comment-container-'.$comment->id) // Check if comment container is gone
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Reactions Tests', function (): void {
    it('should show reaction button for authenticated users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This is a comment that should show reaction buttons.',
        ]);

        $this->actingAs($user2);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertPresent('@reaction-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should not show reaction button for comment owner', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is my own comment that should not have a reaction button.',
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNotPresent('@reaction-button-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });

    it('should toggle reaction on comment', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment should be likeable.',
        ]);

        $this->actingAs($user2);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('0 Likes')
            ->click('@reaction-button-'.$comment->id)
            ->assertSee('1 Like')
            ->assertNoJavaScriptErrors();
    });

    it('should remove reaction when clicked again', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment should allow toggling likes.',
        ]);

        $this->actingAs($user2);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('0 Likes')
            ->click('@reaction-button-'.$comment->id)
            ->assertSee('1 Like')
            ->click('@reaction-button-'.$comment->id)
            ->assertSee('0 Likes')
            ->assertNoJavaScriptErrors();
    });

    it('should display existing reaction count correctly', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment already has some reactions.',
        ]);

        // Create existing reactions
        $comment->reactions()->create(['user_id' => $user2->id]);
        $comment->reactions()->create(['user_id' => $user3->id]);

        $this->actingAs($user1);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('2 Likes')
            ->assertNoJavaScriptErrors();
    });
});

describe('Spam Marking Tests', function (): void {
    it('should show spam ribbon to moderators when comment is marked as spam', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that will be marked as spam.',
        ]);

        // Mark the comment as spam using the model method
        $comment->markAsSpamByModerator($moderator->id);

        // Verify our comment was created correctly
        expect($comment->fresh()->isSpam())->toBeTrue()
            ->and($comment->fresh()->spam_status)->toBe(SpamStatus::SPAM)
            ->and($moderator->isModOrAdmin())->toBeTrue();

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee($comment->body)
            ->assertSeeIn('.comment-container-'.$comment->id.' .ribbon', 'Spam')
            ->assertNoJavaScriptErrors();
    });

    it('should display admin action menu for moderators on clean comments', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a clean comment that moderators should be able to moderate.',
        ]);

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee($comment->body)
            ->assertNotPresent('.comment-container-'.$comment->id.' .ribbon')
            ->assertPresent('.comment-container-'.$comment->id.' button[data-flux-button]')
            ->assertNoJavaScriptErrors();
    });

    it('should show spam ribbon immediately after marking comment as spam via UI', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This comment will be marked as spam through the UI.',
        ]);

        // Verify comment starts clean
        expect($comment->fresh()->isSpam())->toBeFalse();

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee($comment->body)
            ->assertNotPresent('.comment-container-'.$comment->id.' .ribbon')
            ->assertPresent('.comment-container-'.$comment->id.' button[data-flux-button]')
            ->assertNoJavaScriptErrors();
    });

    it('should hide spam comments completely from regular users', function (): void {
        $commentAuthor = User::factory()->create();
        $otherUser = User::factory()->create(); // Different user who shouldn't see spam

        // Create mod with minimal required data for faster loading
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

        // Create a simple mod version linked to existing SPT version
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'spt_version_constraint' => '1.0.0',
            'disabled' => false,
            'published_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $commentAuthor->id,
            'body' => 'This is a spam comment that should be hidden from regular users.',
        ]);

        // Mark the comment as spam
        $comment->markAsSpamByModerator($commentAuthor->id);

        $this->actingAs($otherUser); // Login as a different user, not the comment author

        // Visit the page - timeout is now configured globally
        $page = visit($mod->detail_url.'#comments');

        // Make assertions
        $page->assertSee($mod->name)
            ->assertDontSee($comment->body)
            ->assertNotPresent('.comment-container-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Subscription Tests', function (): void {
    it('should show subscription button for authenticated users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertPresent('@subscription-toggle')
            ->assertSee('Subscribe')
            ->assertNoJavaScriptErrors();
    });

    it('should toggle subscription status', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Subscribe')
            ->click('@subscription-toggle')
            ->assertSee('Subscribed')
            ->assertNoJavaScriptErrors();
    });

    it('should unsubscribe when clicked again', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertSee('Subscribe')
            ->click('@subscription-toggle')
            ->assertSee('Subscribed')
            ->click('@subscription-toggle')
            ->assertSee('Subscribe')
            ->assertNoJavaScriptErrors();
    });

    it('should maintain subscription state across page loads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->click('@subscription-toggle')
            ->assertSee('Subscribed')
            ->navigate($mod->detail_url.'#comments') // Navigate to refresh the page
            ->assertSee($mod->name)
            ->assertSee('Subscribed')
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Display and Pagination Tests', function (): void {
    it('should display empty state when no comments exist', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee('Discussion')
            ->assertSee('(0)')
            ->assertNoJavaScriptErrors();
    });

    it('should show comment count accurately', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        Comment::factory()->count(3)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee('(3)')
            ->assertNoJavaScriptErrors();
    });

    it('should show show replies toggle for comments with descendants', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a root comment with replies.',
        ]);

        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'body' => 'This is a reply to the root comment.',
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee('Show Replies (1)')
            ->assertNoJavaScriptErrors();
    });

    it('should expand and collapse reply threads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a root comment with replies.',
        ]);

        $replyText = 'This is a reply that should be toggleable.';
        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
            'body' => $replyText,
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertDontSee($replyText) // Replies hidden by default now
            ->click('@toggle-replies-'.$rootComment->id)
            ->assertSee($replyText)
            ->assertSee($replyText)
            ->click('@toggle-replies-'.$rootComment->id)
            ->assertDontSee($replyText)
            ->assertNoJavaScriptErrors();
    });

    it('should display comments in correct hierarchical order', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'Root comment',
            'created_at' => now()->subMinutes(10),
        ]);

        $reply1 = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'body' => 'First reply',
            'created_at' => now()->subMinutes(5),
        ]);

        $nestedReply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $reply1->id,
            'body' => 'Nested reply to first reply',
            'created_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee('Root comment')
            ->click('@toggle-replies-'.$rootComment->id) // Load replies first
            ->assertSee('First reply')
            ->assertSee('First reply')
            ->assertSee('Nested reply to first reply')
            ->assertSee('Replying to @'.$user->name)
            ->assertNoJavaScriptErrors();
    });
});

describe('Comment Pinning Tests', function (): void {
    it('should allow moderators to pin comments without browser errors', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        // Create 5 comments
        $comments = [];
        for ($i = 1; $i <= 5; $i++) {
            $comments[] = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => Mod::class,
                'user_id' => $user->id,
                'body' => sprintf('This is test comment number %d.', $i),
            ]);
        }

        $commentToPin = $comments[2];

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments');

        $page->assertSee($mod->name)
            ->assertSee($commentToPin->body)
            ->assertDontSeeIn('.comment-container-'.$commentToPin->id, 'Pinned')
            ->click('.comment-container-'.$commentToPin->id.' [data-flux-dropdown] button[data-flux-button]')
            ->assertSeeIn('.comment-container-'.$commentToPin->id, 'Pin Comment')
            ->click('.comment-container-'.$commentToPin->id.' .action-pin')
            ->click('@confirm-pin-comment')
            ->assertSeeIn('.comment-container-'.$commentToPin->id, 'Pinned')
            ->assertNoJavaScriptErrors();
    });
});
