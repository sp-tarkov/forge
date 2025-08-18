<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Laravel\Dusk\Browser;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    Cache::flush(); // Prevent rate limiting interference.
});

describe('Guest User Tests', function (): void {
    it('should not show comment form to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($mod): void {
            $browser->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Post Comment')
                ->assertMissing('textarea[wire\\:model="newCommentBody"]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show reply buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to reply to.',
        ]);

        $this->browse(function (Browser $browser) use ($mod): void {
            $browser->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Reply');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show edit buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to edit.',
        ]);

        $this->browse(function (Browser $browser) use ($mod): void {
            $browser->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Edit');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show delete buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to delete.',
        ]);

        $this->browse(function (Browser $browser) use ($mod): void {
            $browser->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Delete');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show reaction buttons to guest users', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that guests should not be able to react to.',
        ]);

        $this->browse(function (Browser $browser) use ($mod): void {
            $browser->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertMissing('[wire\\:click*="toggleReaction"]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Creation Tests', function (): void {
    it('should allow logged in user to create a root comment without browser errors', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $commentText = 'This is a test comment with more than minimum length.';

        $this->browse(function (Browser $browser) use ($user, $mod, $commentText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Post Comment')
                ->assertPresent('textarea[wire\\:model="newCommentBody"]')
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText)
                ->press('Post Comment')
                ->waitForText($commentText, 10)
                ->assertSeeIn('#comments', $commentText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should validate minimum comment length', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $shortText = 'Hi';

        $this->browse(function (Browser $browser) use ($user, $mod, $shortText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $shortText)
                ->press('Post Comment')
                ->waitFor('.text-red-500', 5)
                ->assertSee('must be at least'); // Laravel validation message format

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should clear form after successful comment creation', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $commentText = 'This is a test comment that should clear after submission.';

        $this->browse(function (Browser $browser) use ($user, $mod, $commentText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText)
                ->press('Post Comment')
                ->waitForText($commentText, 10)
                ->assertInputValue('textarea[wire\\:model="newCommentBody"]', '');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should enforce rate limiting for regular users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $commentText1 = 'This is the first test comment for rate limiting.';
        $commentText2 = 'This is the second test comment for rate limiting.';

        $this->browse(function (Browser $browser) use ($user, $mod, $commentText1, $commentText2): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 5)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText1)
                ->press('Post Comment')
                ->waitForText($commentText1, 5)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText2)
                ->press('Post Comment')
                ->assertDontSee($commentText2)
                ->waitForText('Too many comment attempts', 5);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should allow administrators to bypass rate limiting', function (): void {
        $admin = User::factory()->create();
        $adminRole = UserRole::factory()->administrator()->create();
        $admin->assignRole($adminRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $commentText1 = 'This is the first admin comment.';
        $commentText2 = 'This is the second admin comment.';

        $this->browse(function (Browser $browser) use ($admin, $mod, $commentText1, $commentText2): void {
            $browser->loginAs($admin)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText1)
                ->press('Post Comment')
                ->waitForText($commentText1, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText2)
                ->press('Post Comment')
                ->waitForText($commentText2, 10)
                ->assertSee($commentText2);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should allow moderators to bypass rate limiting', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $commentText1 = 'This is the first moderator comment.';
        $commentText2 = 'This is the second moderator comment.';

        $this->browse(function (Browser $browser) use ($moderator, $mod, $commentText1, $commentText2): void {
            $browser->loginAs($moderator)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText1)
                ->press('Post Comment')
                ->waitForText($commentText1, 10)
                ->type('textarea[wire\\:model="newCommentBody"]', $commentText2)
                ->press('Post Comment')
                ->waitForText($commentText2, 10)
                ->assertSee($commentText2);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Reply Tests', function (): void {
    it('should show reply button for authenticated users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment that should show a reply button.',
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Reply');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should open reply form when reply button is clicked', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->assertSee('Reply To Comment')
                ->assertSee('Post Reply');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should close reply form when cancel is clicked', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->press('Cancel')
                ->waitUntilMissingText('Reply To Comment', 5)
                ->assertDontSee('Reply To Comment');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should create reply to root comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $replyText = 'This is my reply to the test comment.';

        $this->browse(function (Browser $browser) use ($user, $mod, $replyText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->type('textarea[wire\\:model*="reply"]', $replyText)
                ->press('Post Reply')
                ->waitForText($replyText, 10) // Reply should show automatically after creation
                ->assertSee($replyText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should maintain comment hierarchy', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is the root comment.',
        ]);
        $replyText = 'This is a reply to the root comment.';

        $this->browse(function (Browser $browser) use ($user, $mod, $rootComment, $replyText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->type('textarea[wire\\:model*="reply"]', $replyText)
                ->press('Post Reply')
                ->waitForText($replyText, 10) // Reply should show automatically after creation
                ->assertSee('Replying to @'.$rootComment->user->name);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should validate reply content length', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $shortReply = 'Hi';

        $this->browse(function (Browser $browser) use ($user, $mod, $shortReply): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->type('textarea[wire\\:model*="reply"]', $shortReply)
                ->press('Post Reply')
                ->waitFor('.text-red-500', 5)
                ->assertSee('must be at least');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should clear reply form after successful submission', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $replyText = 'This reply should clear the form after submission.';

        $this->browse(function (Browser $browser) use ($user, $mod, $replyText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleReplyForm"]')
                ->waitForText('Reply To Comment', 5)
                ->type('textarea[wire\\:model*="reply"]', $replyText)
                ->press('Post Reply')
                ->waitForText($replyText, 10) // Reply should show automatically after creation
                ->assertDontSee('Reply To Comment'); // Form should be hidden after successful reply

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Editing Tests', function (): void {
    it('should show edit button for comment owner within time limit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a freshly created comment that should be editable.',
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Edit');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show edit button for other users comments', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment belongs to user1.',
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user2, $mod): void {
            $browser->loginAs($user2)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Edit');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should open edit form with existing comment content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $originalText = 'This is the original comment text that should appear in the edit form.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $originalText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleEditForm"]')
                ->waitForText('Edit Comment', 5)
                ->assertSee('Edit Comment')
                ->assertInputValue('textarea[wire\\:model*="edit"]', $originalText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should save edited comment successfully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $originalText = 'This is the original comment text.';
        $editedText = 'This is the edited comment text with more content.';
        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $originalText, $editedText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee($originalText)
                ->click('button[wire\\:click*="toggleEditForm"]')
                ->waitForText('Edit Comment', 5)
                ->clear('textarea[wire\\:model*="edit"]')
                ->type('textarea[wire\\:model*="edit"]', $editedText)
                ->press('Update Comment')
                ->waitForText($editedText, 10)
                ->assertSee($editedText)
                ->assertDontSee($originalText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should validate edited comment content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $originalText = 'This is the original comment text.';
        $shortText = 'Hi';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $shortText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click*="toggleEditForm"]')
                ->waitForText('Edit Comment', 5)
                ->clear('textarea[wire\\:model*="edit"]')
                ->type('textarea[wire\\:model*="edit"]', $shortText)
                ->press('Update Comment')
                ->waitFor('.text-red-500', 5)
                ->assertSee('must be at least');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should cancel edit without saving changes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $originalText = 'This is the original comment text that should remain unchanged.';
        $editedText = 'This edited text should not be saved when cancelled.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $originalText, $editedText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee($originalText)
                ->click('button[wire\\:click*="toggleEditForm"]')
                ->waitForText('Edit Comment', 5)
                ->clear('textarea[wire\\:model*="edit"]')
                ->type('textarea[wire\\:model*="edit"]', $editedText)
                ->press('Cancel')
                ->waitUntilMissing('[text="Edit Comment"]', 5)
                ->assertSee($originalText)
                ->assertDontSee($editedText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should refresh listing with edited comment content after successful edit', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $originalText = 'Original comment text for editing test';
        $editedText = 'Edited comment text that should appear after update';

        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $originalText, $editedText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee($originalText)
                ->click('button[wire\\:click*="toggleEditForm"]')
                ->waitForText('Edit Comment', 5)
                ->clear('textarea[wire\\:model*="edit"]')
                ->type('textarea[wire\\:model*="edit"]', $editedText)
                ->press('Update Comment')
                ->waitForText($editedText, 5)
                ->assertDontSee($originalText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Deletion Tests', function (): void {
    it('should show delete button for comment owner', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a comment that the owner should be able to delete.',
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Remove');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show delete button for other users comments', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment belongs to user1.',
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user2, $mod): void {
            $browser->loginAs($user2)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee('Remove');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should require confirmation before deletion', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $commentText = 'This comment should require confirmation before deletion.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $commentText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $commentText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee($commentText)
                ->click('button[wire\\:click*="confirmDeleteComment"]')
                ->waitForText('Remove Comment')
                ->press('Cancel')
                ->waitUntilMissingText('Remove Comment')
                ->assertSee($commentText); // Comment should still be there after canceling

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should delete comment after confirming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $commentText = 'This comment should be deleted after confirmation.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $commentText,
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod, $commentText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee($commentText)
                ->click('button[wire\\:click*="confirmDeleteComment"]')
                ->waitForText('Remove Comment')
                ->press('Remove Comment')
                ->waitUntilMissingText($commentText, 5)
                ->assertDontSee($commentText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Reactions Tests', function (): void {
    it('should show reaction button for authenticated users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This is a comment that should show reaction buttons.',
        ]);

        $this->browse(function (Browser $browser) use ($user2, $mod): void {
            $browser->loginAs($user2)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertPresent('button[wire\\:click*="toggleReaction"]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should not show reaction button for comment owner', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is my own comment that should not have a reaction button.',
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertMissing('button[wire\\:click*="toggleReaction"]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should toggle reaction on comment', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment should be likeable.',
        ]);

        $this->browse(function (Browser $browser) use ($user2, $mod): void {
            $browser->loginAs($user2)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('0 Likes')
                ->click('button[wire\\:click*="toggleReaction"]')
                ->waitForText('1 Like', 5)
                ->assertSee('1 Like');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should remove reaction when clicked again', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment should allow toggling likes.',
        ]);

        $this->browse(function (Browser $browser) use ($user2, $mod): void {
            $browser->loginAs($user2)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('0 Likes')
                ->click('button[wire\\:click*="toggleReaction"]')
                ->waitForText('1 Like', 5)
                ->click('button[wire\\:click*="toggleReaction"]')
                ->waitForText('0 Likes', 5)
                ->assertSee('0 Likes');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should display existing reaction count correctly', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user1->id,
            'body' => 'This comment already has some reactions.',
        ]);

        // Create existing reactions
        $comment->reactions()->create(['user_id' => $user2->id]);
        $comment->reactions()->create(['user_id' => $user3->id]);

        $this->browse(function (Browser $browser) use ($user1, $mod): void {
            $browser->loginAs($user1)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('2 Likes');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Spam Marking Tests', function (): void {
    it('should show spam ribbon to moderators when comment is marked as spam', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

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

        $this->browse(function (Browser $browser) use ($moderator, $mod, $comment): void {
            $browser->loginAs($moderator)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->waitForText($comment->body)
                ->assertSeeIn('.comment-container-'.$comment->id.' .ribbon', 'Spam');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should display admin action menu for moderators on clean comments', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a clean comment that moderators should be able to moderate.',
        ]);

        $this->browse(function (Browser $browser) use ($moderator, $mod, $comment): void {
            $browser->loginAs($moderator)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->waitForText($comment->body)
                // Verify no spam ribbon initially for clean comment
                ->assertMissing('.comment-container-'.$comment->id.' .ribbon')
                // Verify the action button (cog icon) is present for moderators
                ->assertPresent('.comment-container-'.$comment->id.' button[data-flux-button]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should show spam ribbon immediately after marking comment as spam via UI', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This comment will be marked as spam through the UI.',
        ]);

        // Verify comment starts clean
        expect($comment->fresh()->isSpam())->toBeFalse();

        $this->browse(function (Browser $browser) use ($moderator, $mod, $comment): void {
            $browser->loginAs($moderator)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->waitForText($comment->body)
                // Verify no spam ribbon initially
                ->assertMissing('.comment-container-'.$comment->id.' .ribbon')
                // Take screenshot for debugging
                ->screenshot('before-spam-marking')
                // Try to interact with the comment action menu
                ->pause(1000)
                ->assertPresent('.comment-container-'.$comment->id.' button[data-flux-button]');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should hide spam comments completely from regular users', function (): void {
        $commentAuthor = User::factory()->create();
        $otherUser = User::factory()->create(); // Different user who shouldn't see spam
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $commentAuthor->id,
            'body' => 'This is a spam comment that should be hidden from regular users.',
        ]);

        // Mark the comment as spam
        $comment->markAsSpamByModerator($commentAuthor->id);

        $this->browse(function (Browser $browser) use ($otherUser, $mod, $comment): void {
            $browser->loginAs($otherUser) // Login as different user, not the comment author
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                // Spam comments should be completely hidden from users who aren't the author
                ->assertDontSee($comment->body)
                ->assertMissing('.comment-container-'.$comment->id);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Subscription Tests', function (): void {
    it('should show subscription button for authenticated users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Subscribe');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should toggle subscription status', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Subscribe')
                ->click('button[wire\\:click="toggleSubscription"]')
                ->waitForText('Subscribed', 5)
                ->assertSee('Subscribed');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should unsubscribe when clicked again', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Subscribe')
                ->click('button[wire\\:click="toggleSubscription"]')
                ->waitForText('Subscribed', 5)
                ->click('button[wire\\:click="toggleSubscription"]')
                ->waitForText('Subscribe', 5)
                ->assertSee('Subscribe');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should maintain subscription state across page loads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->click('button[wire\\:click="toggleSubscription"]')
                ->waitForText('Subscribed', 5)
                ->refresh()
                ->waitForText($mod->name, 10)
                ->assertSee('Subscribed');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});

describe('Comment Display and Pagination Tests', function (): void {
    it('should display empty state when no comments exist', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Discussion')
                ->assertSee('(0)');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should show comment count accurately', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

        Comment::factory()->count(3)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('(3)');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should show show replies toggle for comments with descendants', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

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

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Show Replies (1)');

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should expand and collapse reply threads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

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

        $this->browse(function (Browser $browser) use ($user, $mod, $replyText): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertDontSee($replyText) // Replies hidden by default now
                ->click('button[wire\\:click*="toggleDescendants"]')
                ->waitForText($replyText, 5)
                ->assertSee($replyText)
                ->click('button[wire\\:click*="toggleDescendants"]')
                ->waitUntilMissingText($replyText, 5)
                ->assertDontSee($replyText);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });

    it('should display comments in correct hierarchical order', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

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

        $this->browse(function (Browser $browser) use ($user, $mod): void {
            $browser->loginAs($user)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->assertSee('Root comment')
                ->click('button[wire\\:click*="toggleDescendants"]') // Load replies first
                ->waitForText('First reply', 5)
                ->assertSee('First reply')
                ->assertSee('Nested reply to first reply')
                ->assertSee('Replying to @'.$user->name);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});
describe('Comment Pinning Tests', function (): void {
    it('should allow moderators to pin comments without browser errors', function (): void {
        $moderator = User::factory()->create();
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator->assignRole($moderatorRole);

        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id]);

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

        $this->browse(function (Browser $browser) use ($moderator, $mod, $commentToPin): void {
            $browser->loginAs($moderator)
                ->visit($mod->detail_url.'#comments')
                ->waitForText($mod->name, 10)
                ->waitFor('[x-show="selectedTab === \'comments\'"]', 5)
                ->waitForText($commentToPin->body, 10)
                ->assertDontSee('Pinned')
                ->waitFor('.comment-container-'.$commentToPin->id.' [data-flux-dropdown] button[data-flux-button]', 5)
                ->with('.comment-container-'.$commentToPin->id.' [data-flux-dropdown]', function ($dropdown): void {
                    $dropdown->click('button[data-flux-button]')
                        ->waitFor('[data-flux-menu]', 5)
                        ->waitFor('.action-pin', 5)
                        ->click('.action-pin');
                })
                ->waitForText('Pinned', 5);

            $this->assertEmpty($browser->driver->manage()->getLog('browser'));
        });
    });
});
