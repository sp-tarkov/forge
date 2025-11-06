<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('guest permissions', function (): void {
    it('should not allow a guest to create a comment', function (): void {
        $mod = Mod::factory()->create();

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'This is a test comment.')
            ->call('createComment')
            ->assertForbidden();
    });
});

describe('authenticated user permissions', function (): void {
    it('should allow a user to create a comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'This is a test comment.')
            ->call('createComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a test comment.',
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
    });

    it('should not allow an unverified user to create a comment', function (): void {
        $user = User::factory()->unverified()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'This is a test comment.')
            ->call('createComment')
            ->assertForbidden();

        $this->assertDatabaseMissing('comments', [
            'body' => 'This is a test comment.',
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);
    });
});

describe('unpublished mod restrictions', function (): void {
    it('should not allow creating comments on unpublished mods even by owner', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['published_at' => null, 'owner_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'Comment on unpublished mod')
            ->call('createComment')
            ->assertForbidden();
    });

    it('should not allow comments on mods that are not yet published', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Future publication

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'Comment on unpublished mod')
            ->call('createComment')
            ->assertForbidden();
    });

    it('should not allow moderators to comment on unpublished mods', function (): void {
        $moderator = User::factory()->moderator()->create();

        $mod = Mod::factory()->create(['published_at' => null]);

        Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'Moderator comment on unpublished mod')
            ->call('createComment')
            ->assertForbidden();
    });

    it('should not allow administrators to comment on unpublished mods', function (): void {
        $admin = User::factory()->admin()->create();

        $mod = Mod::factory()->create(['published_at' => null]);

        Livewire::actingAs($admin)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'Admin comment on unpublished mod')
            ->call('createComment')
            ->assertForbidden();
    });
});

describe('comment validation', function (): void {
    it('should not allow creating comments with empty body', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', '')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody' => 'required']);
    });

    it('should not allow creating comments that are too short', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', 'Hi')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody' => 'min']);
    });

    it('should not allow creating comments with only whitespace that becomes too short after trimming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // These should all fail validation because they trim to less than 3 characters
        $testCases = [
            '  Hi  ',  // trims to "Hi" (2 chars)
            ' A ',     // trims to "A" (1 char)
            '   ',     // trims to "" (0 chars)
            "\t\n  Hi  \n\t",  // trims to "Hi" (2 chars)
        ];

        foreach ($testCases as $testCase) {
            Livewire::actingAs($user)
                ->test(CommentComponent::class, ['commentable' => $mod])
                ->set('newCommentBody', $testCase)
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        }
    });

    it('should allow creating comments that are long enough after trimming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // This should pass validation because it trims to "Hello" (5 chars)
        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', '   Hello   ')
            ->call('createComment')
            ->assertHasNoErrors();

        // Verify the comment was created with trimmed content
        $comment = Comment::query()->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->latest()
            ->first();

        expect($comment->body)->toBe('Hello');
    });

    it('should not allow creating comments that are too long', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $longText = str_repeat('a', 10001);

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', $longText)
            ->call('createComment')
            ->assertHasErrors(['newCommentBody' => 'max']);
    });
});

describe('special content handling', function (): void {
    it('should properly handle unicode characters in comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $unicodeContent = 'Hello 你好 مرحبا émojis: 😀🎉🚀 special: ñáéíóú';

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', $unicodeContent)
            ->call('createComment')
            ->assertHasNoErrors();

        $comment = Comment::query()->latest()->first();
        expect($comment->body)->toBe($unicodeContent);
    });

    it('should handle markdown special characters without breaking', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $markdownContent = '**bold** _italic_ `code` [link](http://example.com) # heading';

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', $markdownContent)
            ->call('createComment')
            ->assertHasNoErrors();

        $comment = Comment::query()->latest()->first();
        expect($comment->body)->toBe($markdownContent);
    });
});

describe('rate limiting', function (): void {
    it('should enforce rate limiting (30 seconds between comments)', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod]);

        // First comment should succeed
        $component->set('newCommentBody', 'First comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Second comment immediately after should show rate limit error
        $component->set('newCommentBody', 'Second comment')
            ->call('createComment')
            ->assertHasErrors('newCommentBody')
            ->assertSee('Too many comment attempts')
            ->assertSee('seconds before commenting again'); // Check for rate limiting message with seconds remaining

        // Verify only one comment was created
        $comments = Comment::query()->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->count();

        expect($comments)->toBe(1);
    });

    it('should allow administrators to bypass rate limiting', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(CommentComponent::class, ['commentable' => $mod]);

        // First comment should succeed
        $component->set('newCommentBody', 'First admin comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Second comment immediately after should also succeed (no rate limit for admins)
        $component->set('newCommentBody', 'Second admin comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Verify both comments were created
        $comments = Comment::query()->where('user_id', $admin->id)
            ->where('commentable_id', $mod->id)
            ->count();

        expect($comments)->toBe(2);
    });

    it('should allow moderators to bypass rate limiting', function (): void {
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod]);

        // First comment should succeed
        $component->set('newCommentBody', 'First moderator comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Second comment immediately after should also succeed (no rate limit for moderators)
        $component->set('newCommentBody', 'Second moderator comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Verify both comments were created
        $comments = Comment::query()->where('user_id', $moderator->id)
            ->where('commentable_id', $mod->id)
            ->count();

        expect($comments)->toBe(2);
    });
});

describe('security', function (): void {
    it('should prevent SQL injection through comment content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $sqlInjectionAttempt = "'; DROP TABLE comments; --";

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('newCommentBody', $sqlInjectionAttempt)
            ->call('createComment')
            ->assertHasNoErrors();

        // Verify the comment was created with the exact content (properly escaped)
        $comment = Comment::query()->latest()->first();
        expect($comment->body)->toBe($sqlInjectionAttempt)
            ->and(Comment::query()->count())->toBeGreaterThan(0);
    });

    it('should handle invalid data types gracefully', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod]);

        // Count comments before
        $commentCountBefore = Comment::query()->count();

        // Livewire's property system will throw a TypeError when trying to set an array to string
        $exceptionThrown = false;
        try {
            $component->set('newCommentBody', ['array', 'of', 'values']);
        } catch (TypeError) {
            $exceptionThrown = true;
        }

        // Expect an exception thrown, and no comment created.
        expect($exceptionThrown)->toBeTrue()
            ->and(Comment::query()->count())->toBe($commentCountBefore);
    });
});

describe('user profile comments', function (): void {
    it('should verify user profiles can receive comments', function (): void {
        $user = User::factory()->create();

        expect($user->canReceiveComments())->toBeTrue();
    });

    it('should allow commenting on user profiles', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        Livewire::actingAs($commenter)
            ->test(CommentComponent::class, ['commentable' => $profileOwner])
            ->set('newCommentBody', 'Nice profile!')
            ->call('createComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'Nice profile!',
            'user_id' => $commenter->id,
            'commentable_id' => $profileOwner->id,
            'commentable_type' => User::class,
        ]);
    });

    it('should allow users to comment on their own profile', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $user])
            ->set('newCommentBody', 'Welcome to my profile!')
            ->call('createComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'Welcome to my profile!',
            'user_id' => $user->id,
            'commentable_id' => $user->id,
            'commentable_type' => User::class,
        ]);
    });

    it('should enforce rate limiting on user wall comments', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        $component = Livewire::actingAs($commenter)
            ->test(CommentComponent::class, ['commentable' => $profileOwner]);

        // First comment should succeed
        $component->set('newCommentBody', 'First comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Second comment immediately after should show rate limit error
        $component->set('newCommentBody', 'Second comment')
            ->call('createComment')
            ->assertHasErrors('newCommentBody')
            ->assertSee('Too many comment attempts');

        // Verify only one comment was created
        $comments = Comment::query()->where('user_id', $commenter->id)
            ->where('commentable_id', $profileOwner->id)
            ->where('commentable_type', User::class)
            ->count();

        expect($comments)->toBe(1);
    });
});

describe('mod publication logic', function (): void {
    it('should verify mod publication logic works in canReceiveComments', function (): void {
        // Published mod should allow comments
        $publishedMod = Mod::factory()->create(['published_at' => now()->subHour()]);
        expect($publishedMod->canReceiveComments())->toBeTrue();

        // Unpublished mod should not allow comments
        $unpublishedMod = Mod::factory()->create(['published_at' => null]);
        expect($unpublishedMod->canReceiveComments())->toBeFalse();

        // Future published mod should not allow comments yet
        $futureMod = Mod::factory()->create(['published_at' => now()->addHour()]);
        expect($futureMod->canReceiveComments())->toBeFalse();
    });
});

describe('body trimming', function (): void {
    it('should trim whitespace when creating a comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $comment = $mod->comments()->create([
            'body' => '  This is a comment with leading and trailing spaces  ',
            'user_id' => $user->id,
        ]);

        expect($comment->body)->toBe('This is a comment with leading and trailing spaces');
    });

    it('should trim whitespace when updating a comment', function (): void {
        $comment = Comment::factory()->create([
            'body' => 'Original comment body',
        ]);

        $comment->update([
            'body' => '  Updated comment with spaces  ',
        ]);

        expect($comment->fresh()->body)->toBe('Updated comment with spaces');
    });

    it('should trim whitespace when directly setting the body attribute', function (): void {
        $comment = Comment::factory()->create([
            'body' => 'Original comment body',
        ]);

        $comment->body = '  Direct assignment with spaces  ';
        $comment->save();

        expect($comment->fresh()->body)->toBe('Direct assignment with spaces');
    });

    it('should handle newlines and tabs properly', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $comment = $mod->comments()->create([
            'body' => "\t\n  This comment has tabs and newlines\n\t  ",
            'user_id' => $user->id,
        ]);

        expect($comment->body)->toBe('This comment has tabs and newlines');
    });

    it('should preserve internal whitespace', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $comment = $mod->comments()->create([
            'body' => '  This  has  multiple  spaces  between  words  ',
            'user_id' => $user->id,
        ]);

        expect($comment->body)->toBe('This  has  multiple  spaces  between  words');
    });

    it('should handle empty strings after trimming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $comment = $mod->comments()->create([
            'body' => '   ',
            'user_id' => $user->id,
        ]);

        expect($comment->body)->toBe('');
    });
});
