<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Jobs\CheckCommentForSpam;
use App\Jobs\TranslateComment;
use App\Models\Comment;
use App\Models\CommentVersion;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CommentSpamService;
use App\Support\DataTransferObjects\CommentTranslationResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

/**
 * Helper function to create a published mod with an owner and a single version.
 */
function createPublishedMod(): Mod
{
    $user = User::factory()->create();
    SptVersion::query()->firstOrCreate(['version' => '1.0.0'], SptVersion::factory()->make(['version' => '1.0.0'])->toArray());

    $mod = Mod::factory()->create(['published_at' => now()->subHour(), 'owner_id' => $user->id]);

    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    return $mod;
}

/**
 * Helper function to create a moderator user.
 */
function createModerator(): User
{
    return User::factory()->moderator()->create();
}

describe('Creation', function (): void {
    describe('permissions', function (): void {
        it('should not allow a guest to create a comment', function (): void {
            $mod = Mod::factory()->create();

            Livewire::test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'This is a test comment.')
                ->call('createComment')
                ->assertForbidden();
        });

        it('should allow a user to create a comment', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'This is a test comment.')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $mod->id)
                ->where('commentable_type', $mod::class)
                ->first();

            expect($comment)->not->toBeNull()
                ->and($comment->body)->toBe('This is a test comment.');
        });

        it('should not allow an unverified user to create a comment', function (): void {
            $user = User::factory()->unverified()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'This is a test comment.')
                ->call('createComment')
                ->assertForbidden();

            $comment = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $mod->id)
                ->where('commentable_type', $mod::class)
                ->first();

            expect($comment)->toBeNull();
        });
    });

    describe('unpublished mod restrictions', function (): void {
        it('should not allow creating comments on unpublished mods even by owner', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => null, 'owner_id' => $user->id]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Comment on unpublished mod')
                ->call('createComment')
                ->assertForbidden();
        });

        it('should not allow comments on mods that are not yet published', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Future publication

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Comment on unpublished mod')
                ->call('createComment')
                ->assertForbidden();
        });

        it('should not allow moderators to comment on unpublished mods', function (): void {
            $moderator = User::factory()->moderator()->create();

            $mod = Mod::factory()->create(['published_at' => null]);

            Livewire::actingAs($moderator)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Moderator comment on unpublished mod')
                ->call('createComment')
                ->assertForbidden();
        });

        it('should not allow administrators to comment on unpublished mods', function (): void {
            $admin = User::factory()->admin()->create();

            $mod = Mod::factory()->create(['published_at' => null]);

            Livewire::actingAs($admin)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Admin comment on unpublished mod')
                ->call('createComment')
                ->assertForbidden();
        });
    });

    describe('validation', function (): void {
        it('should not allow creating comments with empty body', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody' => 'required']);
        });

        it('should not allow creating comments that are too short', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
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
                    ->test('comment-component', ['commentable' => $mod])
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
                ->test('comment-component', ['commentable' => $mod])
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
                ->test('comment-component', ['commentable' => $mod])
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
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', $unicodeContent)
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe($unicodeContent);
        });

        it('should handle markdown special characters without breaking', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $markdownContent = '**bold** _italic_ `code` [link](http://example.com) # heading';

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', $markdownContent)
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe($markdownContent);
        });
    });

    describe('rate limiting', function (): void {
        it('should enforce rate limiting (30 seconds between comments)', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod]);

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
                ->test('comment-component', ['commentable' => $mod]);

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
                ->test('comment-component', ['commentable' => $mod]);

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
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', $sqlInjectionAttempt)
                ->call('createComment')
                ->assertHasNoErrors();

            // Verify the comment was created with the exact content (properly escaped)
            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe($sqlInjectionAttempt)
                ->and(Comment::query()->count())->toBeGreaterThan(0);
        });

        it('should handle invalid data types gracefully', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $commentCountBefore = Comment::query()->count();

            // Livewire may throw a TypeError or silently reject the invalid type depending on
            // the environment. Either way, no comment should be created from an array value.
            try {
                Livewire::actingAs($user)
                    ->test('comment-component', ['commentable' => $mod])
                    ->set('newCommentBody', ['array', 'of', 'values'])
                    ->call('createComment');
            } catch (TypeError) {
                // Expected in some environments; handled gracefully.
            }

            expect(Comment::query()->count())->toBe($commentCountBefore);
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
                ->test('comment-component', ['commentable' => $profileOwner])
                ->set('newCommentBody', 'Nice profile!')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()
                ->where('user_id', $commenter->id)
                ->where('commentable_id', $profileOwner->id)
                ->where('commentable_type', User::class)
                ->first();

            expect($comment)->not->toBeNull()
                ->and($comment->body)->toBe('Nice profile!');
        });

        it('should allow users to comment on their own profile', function (): void {
            $user = User::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $user])
                ->set('newCommentBody', 'Welcome to my profile!')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $user->id)
                ->where('commentable_type', User::class)
                ->first();

            expect($comment)->not->toBeNull()
                ->and($comment->body)->toBe('Welcome to my profile!');
        });

        it('should enforce rate limiting on user wall comments', function (): void {
            $profileOwner = User::factory()->create();
            $commenter = User::factory()->create();

            $component = Livewire::actingAs($commenter)
                ->test('comment-component', ['commentable' => $profileOwner]);

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
        it('should trim whitespace when creating a comment via Livewire', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '  This is a comment with leading and trailing spaces  ')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe('This is a comment with leading and trailing spaces');
        });

        it('should handle newlines and tabs properly', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', "\t\n  This comment has tabs and newlines\n\t  ")
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe('This comment has tabs and newlines');
        });

        it('should preserve internal whitespace', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '  This  has  multiple  spaces  between  words  ')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->latest()->first();
            expect($comment->body)->toBe('This  has  multiple  spaces  between  words');
        });
    });
});

describe('Replies', function (): void {
    describe('guest restrictions', function (): void {
        it('should not show reply button to guests', function (): void {
            $mod = Mod::factory()->create();
            Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'body' => 'Test comment',
            ]);

            Livewire::test('comment-component', ['commentable' => $mod])
                ->assertSee('Test comment')
                ->assertDontSee('Reply');
        });

        it('should not allow a guest to reply to a comment', function (): void {
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
                ->call('createReply', $parentComment->id)
                ->assertForbidden();
        });
    });

    describe('authenticated user replies', function (): void {
        it('should allow a user to reply to a comment', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            $reply = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $mod->id)
                ->where('commentable_type', $mod::class)
                ->where('parent_id', $parentComment->id)
                ->first();

            expect($reply)->not->toBeNull()
                ->and($reply->body)->toBe('This is a reply.');
        });

        it('should not allow an unverified user to reply to a comment', function (): void {
            $user = User::factory()->unverified()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
                ->call('createReply', $parentComment->id)
                ->assertForbidden();

            $reply = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $mod->id)
                ->where('parent_id', $parentComment->id)
                ->first();

            expect($reply)->toBeNull();
        });

        it('should not allow a user to reply to a comment from a user they have blocked', function (): void {
            $user = User::factory()->create();
            $author = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'user_id' => $author->id,
            ]);

            $user->block($author);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
                ->call('createReply', $parentComment->id)
                ->assertForbidden();

            expect(Comment::query()->where('user_id', $user->id)->where('parent_id', $parentComment->id)->exists())->toBeFalse();
        });

        it('should not allow a user to reply to a comment from a user who has blocked them', function (): void {
            $user = User::factory()->create();
            $author = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'user_id' => $author->id,
            ]);

            $author->block($user);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
                ->call('createReply', $parentComment->id)
                ->assertForbidden();

            expect(Comment::query()->where('user_id', $user->id)->where('parent_id', $parentComment->id)->exists())->toBeFalse();
        });

        it('should hide the reply button on comments from a blocked user', function (): void {
            $user = User::factory()->create();
            $author = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'user_id' => $author->id,
            ]);

            $user->block($author);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->assertDontSeeHtml('data-test="reply-button-'.$parentComment->id.'"');
        });

        it('should show the reply button on comments from users with no block relationship', function (): void {
            $user = User::factory()->create();
            $author = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'user_id' => $author->id,
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->assertSeeHtml('data-test="reply-button-'.$parentComment->id.'"');
        });
    });

    describe('validation', function (): void {
        it('should validate parent comment exists when replying', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $nonExistentCommentId = 99999;

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$nonExistentCommentId.'.body', 'Reply to non-existent comment')
                ->call('createReply', $nonExistentCommentId)
                ->assertNotFound();
        });

        it('should not allow replying to comments from a different mod', function (): void {
            $user = User::factory()->create();
            $mod1 = Mod::factory()->create();
            $mod2 = Mod::factory()->create();

            // Create a comment on mod1
            $comment = Comment::factory()->create([
                'commentable_id' => $mod1->id,
                'commentable_type' => $mod1::class,
            ]);

            // Try to reply to it from mod2's comment manager
            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod2])
                ->set('formStates.reply-'.$comment->id.'.body', 'Cross-mod reply attempt')
                ->call('createReply', $comment->id)
                ->assertNotFound(); // Returns 404 because comment not found in mod2
        });

        it('should not allow replies with only whitespace that becomes too short after trimming', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            // These should all fail validation because they trim to less than 3 characters
            $testCases = [
                '  Hi  ',  // trims to "Hi" (2 chars)
                ' A ',     // trims to "A" (1 char)
                '   ',     // trims to "" (0 chars)
            ];

            foreach ($testCases as $testCase) {
                Livewire::actingAs($user)
                    ->test('comment-component', ['commentable' => $mod])
                    ->set('formStates.reply-'.$parentComment->id.'.body', $testCase)
                    ->call('createReply', $parentComment->id)
                    ->assertHasErrors(['formStates.reply-'.$parentComment->id.'.body']);
            }
        });

        it('should allow replies that are long enough after trimming', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            // This should pass validation because it trims to "Hello" (5 chars)
            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$parentComment->id.'.body', '   Hello   ')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // Verify the reply was created with trimmed content
            $reply = Comment::query()->where('parent_id', $parentComment->id)
                ->where('user_id', $user->id)
                ->latest()
                ->first();

            expect($reply->body)->toBe('Hello');
        });
    });

    describe('hierarchy', function (): void {
        it('should maintain comment hierarchy integrity', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            // Create a root comment
            $rootComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'parent_id' => null,
            ]);

            // Create a valid reply
            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$rootComment->id.'.body', 'Valid reply')
                ->call('createReply', $rootComment->id)
                ->assertHasNoErrors();

            $reply = Comment::query()->where('parent_id', $rootComment->id)->first();
            expect($reply)->not->toBeNull()
                ->and($reply->parent_id)->toBe($rootComment->id);

            // Clear rate limiter before next reply
            RateLimiter::clear('comment-creation:'.$user->id);

            // Can also reply to replies (nested comments are allowed but displayed flat)
            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.reply-'.$reply->id.'.body', 'Reply to reply')
                ->call('createReply', $reply->id)
                ->assertHasNoErrors();
        });
    });

    describe('rate limiting', function (): void {
        it('should enforce rate limiting for replies (same as root comments)', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod]);

            // The first reply should succeed
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'First reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // The second reply immediately after should show rate limit error
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'Second reply')
                ->call('createReply', $parentComment->id)
                ->assertHasErrors('formStates.reply-'.$parentComment->id.'.body');

            // Verify only one reply was created
            $replies = Comment::query()->where('user_id', $user->id)
                ->where('parent_id', $parentComment->id)
                ->count();

            expect($replies)->toBe(1);
        });

        it('should allow administrators to bypass rate limiting for replies', function (): void {
            $admin = User::factory()->admin()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            $component = Livewire::actingAs($admin)
                ->test('comment-component', ['commentable' => $mod]);

            // First reply should succeed
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'First admin reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // Second reply immediately after should also succeed (no rate limit for admins)
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'Second admin reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // Verify both replies were created
            $replies = Comment::query()->where('user_id', $admin->id)
                ->where('parent_id', $parentComment->id)
                ->count();

            expect($replies)->toBe(2);
        });

        it('should allow moderators to bypass rate limiting for replies', function (): void {
            $moderator = User::factory()->moderator()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            $component = Livewire::actingAs($moderator)
                ->test('comment-component', ['commentable' => $mod]);

            // First reply should succeed
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'First moderator reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // Second reply immediately after should also succeed (no rate limit for moderators)
            $component->set('formStates.reply-'.$parentComment->id.'.body', 'Second moderator reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            // Verify both replies were created
            $replies = Comment::query()->where('user_id', $moderator->id)
                ->where('parent_id', $parentComment->id)
                ->count();

            expect($replies)->toBe(2);
        });
    });

    describe('user wall replies', function (): void {
        it('should allow replies on user wall comments', function (): void {
            $profileOwner = User::factory()->create();
            $commenter = User::factory()->create();
            $replier = User::factory()->create();

            // Create initial comment
            $comment = Comment::factory()->create([
                'user_id' => $commenter->id,
                'commentable_id' => $profileOwner->id,
                'commentable_type' => User::class,
                'body' => 'Great profile!',
            ]);

            // Reply to the comment
            Livewire::actingAs($replier)
                ->test('comment-component', ['commentable' => $profileOwner])
                ->set('formStates.reply-'.$comment->id.'.body', 'I agree!')
                ->call('createReply', $comment->id)
                ->assertHasNoErrors();

            $reply = Comment::query()
                ->where('user_id', $replier->id)
                ->where('commentable_id', $profileOwner->id)
                ->where('commentable_type', User::class)
                ->where('parent_id', $comment->id)
                ->first();

            expect($reply)->not->toBeNull()
                ->and($reply->body)->toBe('I agree!');
        });
    });
});

describe('Editing', function (): void {
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

    describe('spam state on edit', function (): void {
        it('preserves the prior spam_status during the recheck and clears the other spam metadata', function (): void {
            Config::set('akismet.enabled', true);
            Queue::fake();

            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $moderator = User::factory()->moderator()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'spam_status' => SpamStatus::CLEAN,
                'spam_metadata' => ['akismet_response' => 'false'],
                'spam_checked_at' => now(),
                'spam_recheck_count' => 2,
                'spam_reviewed_at' => now(),
                'spam_reviewed_by' => $moderator->id,
            ]);
            $comment->versions()->create([
                'body' => 'Original content',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Rewritten content')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();

            expect($comment->spam_status)->toBe(SpamStatus::CLEAN);
            expect($comment->spam_metadata)->toBeNull();
            expect($comment->spam_checked_at)->toBeNull();
            expect($comment->spam_recheck_count)->toBe(0);
            expect($comment->spam_reviewed_at)->toBeNull();
            expect($comment->spam_reviewed_by)->toBeNull();

            Queue::assertPushed(CheckCommentForSpam::class, fn (CheckCommentForSpam $job): bool => $job->comment->id === $comment->id && $job->isRecheck === false);
        });

        it('leaves the spam state untouched and skips the recheck job when Akismet is disabled', function (): void {
            Config::set('akismet.enabled', false);
            Queue::fake();

            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $moderator = User::factory()->moderator()->create();
            $reviewedAt = now()->subMinute();
            $checkedAt = now()->subMinute();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'spam_status' => SpamStatus::CLEAN,
                'spam_metadata' => ['reason' => 'akismet_disabled'],
                'spam_checked_at' => $checkedAt,
                'spam_recheck_count' => 2,
                'spam_reviewed_at' => $reviewedAt,
                'spam_reviewed_by' => $moderator->id,
            ]);
            $comment->versions()->create([
                'body' => 'Original content',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Rewritten content')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();

            expect($comment->spam_status)->toBe(SpamStatus::CLEAN);
            expect($comment->spam_metadata)->toBe(['reason' => 'akismet_disabled']);
            expect($comment->spam_recheck_count)->toBe(2);
            expect($comment->spam_reviewed_by)->toBe($moderator->id);
            expect($comment->spam_checked_at?->toIso8601String())->toBe($checkedAt->toIso8601String());
            expect($comment->spam_reviewed_at?->toIso8601String())->toBe($reviewedAt->toIso8601String());

            Queue::assertNotPushed(CheckCommentForSpam::class);
        });

        it('blocks the author from editing a spam-flagged comment', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            $comment->update(['spam_status' => SpamStatus::SPAM]);
            $comment->versions()->create([
                'body' => 'Spam body',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Rewritten to look legitimate')
                ->call('updateComment', $comment->id)
                ->assertForbidden();

            $comment->refresh();
            $comment->unsetRelation('latestVersion');

            expect($comment->body)->toBe('Spam body');
            expect($comment->spam_status)->toBe(SpamStatus::SPAM);
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
});

describe('Deletion', function (): void {
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
            $this->assertModelExists($comment);
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

            Comment::factory()->create([
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
            $this->assertModelExists($parentComment);
        });
    });

    describe('permissions', function (): void {
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

            Comment::factory()->create([
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

            Comment::factory()->create([
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

    describe('hierarchy', function (): void {
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

            Comment::factory()->create([
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

            Comment::factory()->create([
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

            Comment::factory()->create([
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

            Comment::factory()->create([
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
});

describe('Reactions', function (): void {
    describe('permissions', function (): void {
        it('should not allow users to react to their own comments', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->call('toggleReaction', $comment)
                ->assertForbidden();

            // Verify no reaction was created
            expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeFalse();
        });

        it('should not allow guests to react to comments', function (): void {
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::test('comment-component', ['commentable' => $mod])
                ->call('toggleReaction', $comment)
                ->assertForbidden();
        });

        it('should not allow unverified users to react to comments', function (): void {
            $user = User::factory()->unverified()->create();
            $otherUser = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $otherUser->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->call('toggleReaction', $comment)
                ->assertForbidden();

            // Verify no reaction was created
            expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeFalse();
        });
    });

    describe('guest visibility', function (): void {
        it('should show reaction count to guests without interaction', function (): void {
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'body' => 'Test comment',
            ]);

            // Add some reactions from other users
            $users = User::factory()->count(3)->create();
            foreach ($users as $user) {
                $user->commentReactions()->create(['comment_id' => $comment->id]);
            }

            $component = Livewire::test('comment-component', ['commentable' => $mod])
                ->assertSee('Test comment')
                ->assertDontSee('wire:click="toggleReaction"', false);

            // Check for reaction count - normalize whitespace
            $html = preg_replace('/\s+/', ' ', (string) $component->html());
            expect($html)->toContain('3 Likes');
        });
    });

    describe('toggling', function (): void {
        it('should allow toggling reactions on and off', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $otherUser->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod]);

            // Add reaction
            $component->call('toggleReaction', $comment)
                ->assertHasNoErrors();

            expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeTrue()
                ->and($component->get('userReactionIds'))->toContain($comment->id);

            // Remove reaction
            $component->call('toggleReaction', $comment)
                ->assertHasNoErrors();

            expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeFalse()
                ->and($component->get('userReactionIds'))->not->toContain($comment->id);
        });

        it('should not allow multiple reactions from same user through rapid clicking', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $mod = Mod::factory()->create();

            $comment = Comment::factory()->create([
                'user_id' => $otherUser->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod]);

            // Try to create multiple reactions rapidly
            $component->call('toggleReaction', $comment);
            $component->call('toggleReaction', $comment);
            $component->call('toggleReaction', $comment);

            // Should still only have 1 reaction (or 0 if toggled an odd number of times)
            $reactionCount = $user->commentReactions()
                ->where('comment_id', $comment->id)
                ->count();

            expect($reactionCount)->toBeLessThanOrEqual(1);
        });
    });
});

describe('Display', function (): void {
    it('should correctly display the comment count', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        Comment::factory()->count(5)->create(['commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSeeHtml('<span class="font-normal text-slate-400">(5)</span>');
    });

    it('should correctly paginate comments', function (): void {
        $user = User::factory()->create();
        // Use withoutEvents to skip factory's afterCreating callback (SourceCodeLinks)
        $mod = Mod::withoutEvents(fn () => Mod::factory()->create());
        Comment::factory()->count(15)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $user->id,
        ]);

        $test = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod]);

        $test->assertViewHas('rootComments', fn ($paginator): bool => $paginator->total() === 15);

        $this->assertEquals(2, mb_substr_count((string) $test->html(), '<nav role="navigation" aria-label="Pagination Navigation"'));
    });

    it('should display correct commentable display name for user profiles', function (): void {
        $user = User::factory()->create();

        expect($user->getCommentableDisplayName())->toBe('profile');
    });

    it('should display no comments message before the form when authenticated', function (): void {
        $user = User::factory()->create();
        $commentingUser = User::factory()->create();

        $this->actingAs($commentingUser);

        $response = Livewire::test('comment-component', ['commentable' => $user]);

        // Get the rendered HTML
        $html = $response->html();

        // Find positions of key elements
        $noCommentsPos = mb_strpos($html, 'No comments yet');
        $formPos = mb_strpos($html, 'Post Comment');

        // Assert that "No comments yet" appears before the form
        expect($noCommentsPos)->toBeLessThan($formPos);
    });

    // Downgraded from a Browser test: a guest only sees the empty state and never the create form. The original visit()
    // test only asserted server-rendered output, so a component render assertion covers the same intent without a browser.
    it('should display the empty state and no comment form for guests', function (): void {
        $mod = createPublishedMod();

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('No comments yet')
            ->assertDontSee('Post Comment');
    });

    // Downgraded from a Browser test: comments with descendants render a server-side "show replies" toggle. The original
    // visit() test asserted only the rendered toggle text, so a component render assertion preserves the intent.
    it('should render the show replies toggle for comments with descendants', function (): void {
        $mod = createPublishedMod();

        $parentComment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'body' => 'This is the root comment.',
        ]);

        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
            'body' => 'This is a reply.',
        ]);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('This is the root comment.')
            ->assertSee('reply', false);
    });
});

describe('Versioning', function (): void {
    describe('version creation', function (): void {
        it('creates initial version when comment is created', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'This is a test comment.')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->first();

            expect($comment)->not->toBeNull()
                ->and($comment->versions()->count())->toBe(1)
                ->and($comment->latestVersion->version_number)->toBe(1)
                ->and($comment->latestVersion->body)->toBe('This is a test comment.');
        });

        it('creates new version when comment is edited', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            // Create initial version
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

            expect($comment->versions()->count())->toBe(2)
                ->and($comment->latestVersion->version_number)->toBe(2)
                ->and($comment->latestVersion->body)->toBe('Edited content');
        });

        it('increments version number correctly for multiple edits', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            // Create initial version
            $comment->versions()->create([
                'body' => 'Version 1',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            // Edit 3 times
            for ($i = 2; $i <= 4; $i++) {
                Livewire::actingAs($user)
                    ->test('comment-component', ['commentable' => $mod])
                    ->set('formStates.edit-'.$comment->id.'.body', 'Version '.$i)
                    ->call('updateComment', $comment->id)
                    ->assertHasNoErrors();
            }

            $comment->refresh();

            expect($comment->versions()->count())->toBe(4)
                ->and($comment->latestVersion->version_number)->toBe(4)
                ->and($comment->latestVersion->body)->toBe('Version 4');
        });

        it('stores correct body content in each version', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            // Create initial version
            $comment->versions()->create([
                'body' => 'First version content',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Second version content')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $versions = $comment->versions()->reorder()->orderBy('version_number')->get();

            expect($versions)->toHaveCount(2)
                ->and($versions[0]->body)->toBe('First version content')
                ->and($versions[1]->body)->toBe('Second version content');
        });

        it('returns body from latest version via accessor', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            $comment->versions()->create([
                'body' => 'Initial body',
                'version_number' => 1,
                'created_at' => now(),
            ]);
            $comment->versions()->create([
                'body' => 'Latest body',
                'version_number' => 2,
                'created_at' => now(),
            ]);

            $comment->refresh();

            expect($comment->body)->toBe('Latest body');
        });
    });

    describe('edit time limit removal', function (): void {
        it('allows editing comments older than 5 minutes', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'created_at' => now()->subMinutes(10),
            ]);
            $comment->versions()->create([
                'body' => 'Original',
                'version_number' => 1,
                'created_at' => now()->subMinutes(10),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 10 minutes')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            expect($comment->body)->toBe('Updated after 10 minutes');
        });

        it('allows editing comments older than 1 day', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'created_at' => now()->subDay(),
            ]);
            $comment->versions()->create([
                'body' => 'Original',
                'version_number' => 1,
                'created_at' => now()->subDay(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 1 day')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            expect($comment->body)->toBe('Updated after 1 day');
        });

        it('allows editing comments older than 1 week', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'created_at' => now()->subWeek(),
            ]);
            $comment->versions()->create([
                'body' => 'Original',
                'version_number' => 1,
                'created_at' => now()->subWeek(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Updated after 1 week')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            expect($comment->body)->toBe('Updated after 1 week');
        });
    });

    describe('version history access', function (): void {
        it('allows the comment author, moderators, senior moderators, and admins to view version history', function (): void {
            $author = User::factory()->create();
            $comment = Comment::factory()->create(['user_id' => $author->id]);

            $moderator = User::factory()->moderator()->create();
            $seniorMod = User::factory()->seniorModerator()->create();
            $admin = User::factory()->admin()->create();

            expect($author->can('viewVersionHistory', $comment))->toBeTrue();
            expect($moderator->can('viewVersionHistory', $comment))->toBeTrue();
            expect($seniorMod->can('viewVersionHistory', $comment))->toBeTrue();
            expect($admin->can('viewVersionHistory', $comment))->toBeTrue();
        });

        it('denies regular users from viewing other users version history', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

            expect($user->can('viewVersionHistory', $comment))->toBeFalse();
        });

        it('denies guests from viewing version history', function (): void {
            $comment = Comment::factory()->create();

            // Guest user (null)
            expect(auth()->guest())->toBeTrue()
                ->and(auth()->user()?->can('viewVersionHistory', $comment) ?? false)->toBeFalse();
        });
    });

    describe('version modal', function (): void {
        it('displays version content in modal', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'edited_at' => now(),
            ]);
            $version = $comment->versions()->create([
                'body' => 'Test version content',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->call('openVersionModal', $comment->id, $version->id)
                ->assertSet('showVersionModal', true)
                ->assertSet('viewingVersionId', $version->id)
                ->assertSet('viewingVersionCommentId', $comment->id);

            expect($component->get('viewingVersion.body'))->toBe('Test version content');
        });

        it('shows all versions in dropdown menu for authorized users', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
                'edited_at' => now(),
            ]);
            $comment->versions()->create([
                'body' => 'Version 1',
                'version_number' => 1,
                'created_at' => now()->subMinutes(10),
            ]);
            $comment->versions()->create([
                'body' => 'Version 2',
                'version_number' => 2,
                'created_at' => now(),
            ]);

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod]);

            // The component should render the version history dropdown
            $html = $component->html();
            expect($html)->toContain('edited');
        });
    });

    describe('cascade delete', function (): void {
        it('deletes versions when comment is hard deleted', function (): void {
            $admin = User::factory()->admin()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $admin->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            $comment->versions()->create([
                'body' => 'Version 1',
                'version_number' => 1,
                'created_at' => now(),
            ]);
            $comment->versions()->create([
                'body' => 'Version 2',
                'version_number' => 2,
                'created_at' => now(),
            ]);

            $commentId = $comment->id;
            expect(CommentVersion::query()->where('comment_id', $commentId)->count())->toBe(2);

            // Hard delete the comment
            $comment->delete();

            expect(CommentVersion::query()->where('comment_id', $commentId)->count())->toBe(0);
        });
    });

    describe('body accessor', function (): void {
        it('returns empty string when no versions exist', function (): void {
            $comment = Comment::factory()->create();

            // Without any versions, body should be empty string
            expect($comment->body)->toBe('');
        });

        it('returns latest version body', function (): void {
            $comment = Comment::factory()->create();
            $comment->versions()->create([
                'body' => 'Old version',
                'version_number' => 1,
                'created_at' => now()->subMinute(),
            ]);
            $comment->versions()->create([
                'body' => 'New version',
                'version_number' => 2,
                'created_at' => now(),
            ]);

            $comment->refresh();

            expect($comment->body)->toBe('New version');
        });

        it('updates body when new version is created', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            $comment->versions()->create([
                'body' => 'Original',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            expect($comment->body)->toBe('Original');

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', 'Updated')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            // Need to clear the latestVersion relationship cache
            $comment->unsetRelation('latestVersion');

            expect($comment->body)->toBe('Updated');
        });
    });

    describe('hasBeenEdited helper', function (): void {
        it('returns false for comments that have not been edited and true for edited comments', function (): void {
            $unedited = Comment::factory()->create(['edited_at' => null]);
            $edited = Comment::factory()->create(['edited_at' => now()]);

            expect($unedited->hasBeenEdited())->toBeFalse();
            expect($edited->hasBeenEdited())->toBeTrue();
        });
    });

    describe('getVersionCount helper', function (): void {
        it('returns correct version count', function (): void {
            $comment = Comment::factory()->create();
            $comment->versions()->create([
                'body' => 'Version 1',
                'version_number' => 1,
                'created_at' => now(),
            ]);
            $comment->versions()->create([
                'body' => 'Version 2',
                'version_number' => 2,
                'created_at' => now(),
            ]);
            $comment->versions()->create([
                'body' => 'Version 3',
                'version_number' => 3,
                'created_at' => now(),
            ]);

            expect($comment->getVersionCount())->toBe(3);
        });
    });

    describe('version body trimming', function (): void {
        it('trims whitespace when creating initial version', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '  This has whitespace  ')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()->where('user_id', $user->id)->first();

            expect($comment->body)->toBe('This has whitespace');
        });

        it('trims whitespace when creating new version on edit', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
                'commentable_type' => $mod::class,
            ]);
            $comment->versions()->create([
                'body' => 'Original',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('formStates.edit-'.$comment->id.'.body', '  Edited with whitespace  ')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            $comment->unsetRelation('latestVersion');

            expect($comment->body)->toBe('Edited with whitespace');
        });
    });

    describe('body_html accessor', function (): void {
        it('renders markdown in version body_html and comment body_html via latest version', function (): void {
            $comment = Comment::factory()->create();
            $version = $comment->versions()->create([
                'body' => '**bold text**',
                'version_number' => 1,
                'created_at' => now(),
            ]);

            expect($version->body_html)->toContain('<strong>bold text</strong>');

            $comment->refresh();

            expect($comment->body_html)->toContain('<strong>bold text</strong>');
        });
    });
});

describe('LogFileDetection', function (): void {
    describe('creating comments with log content', function (): void {
        it('should reject creating a comment with Message log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Here is my log: [Message: Something went wrong]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);

            $this->assertDatabaseMissing('comments', [
                'user_id' => $user->id,
                'commentable_id' => $mod->id,
            ]);
        });

        it('should reject creating a comment with Info log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Check this log: [Info: Application started]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with Warning log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Warning log: [Warning: Memory usage high]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with Error log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Error in logs: [Error: Database connection failed]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with timestamped log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Log entry: [2024-01-15 10:30:45.123][Info][')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with timezone and IP log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'Server log: 2024-01-15 10:30:45.123 +00:00|192.168.1.1.8080|')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with MongoDB-like log pattern', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'MongoDB log: "_id": "507f1f77bcf86cd799439011"')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment when log content is mixed with normal text', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', "I'm having an issue. Here's the log:\n\n[Error: Connection timeout]\n\nCan someone help?")
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should allow creating a comment with normal text', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', 'This is a normal comment without log content.')
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()
                ->where('user_id', $user->id)
                ->where('commentable_id', $mod->id)
                ->first();

            expect($comment)->not->toBeNull()
                ->and($comment->body)->toBe('This is a normal comment without log content.');
        });

        it('should allow creating a comment with markdown content', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $markdownContent = "# Heading\n\n## Subheading\n\nSome **bold** and *italic* text with a [link](https://example.com).";

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', $markdownContent)
                ->call('createComment')
                ->assertHasNoErrors();

            $comment = Comment::query()
                ->where('user_id', $user->id)
                ->first();

            expect($comment)->not->toBeNull()
                ->and($comment->body)->toBe($markdownContent);
        });
    });

    describe('replying to comments with log content', function (): void {
        it('should reject replying with log content', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_type' => $mod::class,
                'commentable_id' => $mod->id,
                'body' => 'Parent comment',
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set(sprintf('formStates.reply-%d.body', $parentComment->id), '[Error: Something went wrong]')
                ->call('createReply', $parentComment->id)
                ->assertHasErrors([sprintf('formStates.reply-%d.body', $parentComment->id)]);

            expect(Comment::query()->where('parent_id', $parentComment->id)->count())->toBe(0);
        });

        it('should allow replying with normal content', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $parentComment = Comment::factory()->create([
                'commentable_type' => $mod::class,
                'commentable_id' => $mod->id,
                'body' => 'Parent comment',
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set(sprintf('formStates.reply-%d.body', $parentComment->id), 'This is a valid reply')
                ->call('createReply', $parentComment->id)
                ->assertHasNoErrors();

            $reply = Comment::query()
                ->where('parent_id', $parentComment->id)
                ->where('user_id', $user->id)
                ->first();

            expect($reply)->not->toBeNull()
                ->and($reply->body)->toBe('This is a valid reply');
        });
    });

    describe('editing comments with log content', function (): void {
        it('should reject editing a comment to add log content', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_type' => $mod::class,
                'commentable_id' => $mod->id,
                'body' => 'Original comment',
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set(sprintf('formStates.edit-%d.body', $comment->id), '[Error: Log content added]')
                ->call('updateComment', $comment->id)
                ->assertHasErrors([sprintf('formStates.edit-%d.body', $comment->id)]);

            $comment->refresh();
            expect($comment->body)->toBe('Original comment');
        });

        it('should allow editing a comment with valid content', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_type' => $mod::class,
                'commentable_id' => $mod->id,
                'body' => 'Original comment',
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set(sprintf('formStates.edit-%d.body', $comment->id), 'Updated comment without logs')
                ->call('updateComment', $comment->id)
                ->assertHasNoErrors();

            $comment->refresh();
            expect($comment->body)->toBe('Updated comment without logs');
        });
    });

    describe('error message', function (): void {
        it('should display the correct error message with code paste link', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $component = Livewire::actingAs($user)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '[Error: Test log]')
                ->call('createComment');

            $errors = $component->instance()->getErrorBag();
            $errorMessage = $errors->first('newCommentBody');

            expect($errorMessage)->toContain('Log files detected')
                ->and($errorMessage)->toContain('https://codepaste.sp-tarkov.com');
        });
    });

    describe('moderators and admins', function (): void {
        it('should reject creating a comment with log content for moderators', function (): void {
            $moderator = User::factory()->moderator()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($moderator)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '[Error: Log content from moderator]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });

        it('should reject creating a comment with log content for admins', function (): void {
            $admin = User::factory()->admin()->create();
            $mod = Mod::factory()->create();

            Livewire::actingAs($admin)
                ->test('comment-component', ['commentable' => $mod])
                ->set('newCommentBody', '[Error: Log content from admin]')
                ->call('createComment')
                ->assertHasErrors(['newCommentBody']);
        });
    });
});

describe('Visibility', function (): void {
    beforeEach(function (): void {
        // Enable Akismet so the CommentObserver dispatches the queued spam check (which Queue::fake() then swallows)
        // instead of marking the comment clean inline. The factory-seeded spam_status values these visibility tests
        // rely on (PENDING, SPAM) would otherwise be overwritten by the inline disabled-path branch.
        Config::set('akismet.enabled', true);

        Queue::fake(); // Prevent spam check jobs from running

        $this->mod = Mod::factory()->create();
        $this->author = User::factory()->create();
        $this->otherUser = User::factory()->create();

        // Create moderator with proper role
        $this->moderator = User::factory()->moderator()->create();
    });

    describe('guest visibility', function (): void {
        it('can only see clean comments', function (): void {
            // Create comments with different spam statuses
            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'This is a clean comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'This is a pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'This is a spam comment',
            ]);

            // Test as guest
            Livewire::test('comment-component', ['commentable' => $this->mod])
                ->assertSee('This is a clean comment')
                ->assertDontSee('This is a pending comment')
                ->assertDontSee('This is a spam comment');
        });
    });

    describe('comment author visibility', function (): void {
        it('can see their own pending comments', function (): void {
            // Create comments with different authors
            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'My pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'Other user pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Clean comment visible to all',
            ]);

            // Test as comment author
            Livewire::actingAs($this->author)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('My pending comment')
                ->assertDontSee('Other user pending comment')
                ->assertSee('Clean comment visible to all');
        });

        it('can see their own spam comments', function (): void {
            // Create comments with different authors
            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'My spam comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'Other user spam comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Clean comment visible to all',
            ]);

            // Test as comment author
            Livewire::actingAs($this->author)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('My spam comment')
                ->assertDontSee('Other user spam comment')
                ->assertSee('Clean comment visible to all');
        });
    });

    describe('moderator visibility', function (): void {
        it('can see all comments regardless of spam status', function (): void {
            // Create comments with different spam statuses
            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Clean comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'Pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'Spam comment',
            ]);

            // Test as moderator
            Livewire::actingAs($this->moderator)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('Clean comment')
                ->assertSee('Pending comment')
                ->assertSee('Spam comment');
        });
    });

    describe('regular user visibility', function (): void {
        it('can only see clean comments and their own non-clean comments', function (): void {
            // Create various comments
            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'My pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'My spam comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'Other pending comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::SPAM->value,
                'body' => 'Other spam comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'My clean comment',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Other clean comment',
            ]);

            // Test as the author
            Livewire::actingAs($this->author)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('My pending comment')
                ->assertSee('My spam comment')
                ->assertDontSee('Other pending comment')
                ->assertDontSee('Other spam comment')
                ->assertSee('My clean comment')
                ->assertSee('Other clean comment');
        });
    });

    describe('comment counting', function (): void {
        it('includes users own non-clean comments', function (): void {
            // Create comments
            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::PENDING->value,
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'spam_status' => SpamStatus::SPAM->value,
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'spam_status' => SpamStatus::PENDING->value,
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
            ]);

            // Test as author - should see 3 comments (2 own non-clean + 1 clean)
            Livewire::actingAs($this->author)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('(3)');

            // Test as other user - should see 2 comments (1 own pending + 1 clean)
            Livewire::actingAs($this->otherUser)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('(2)');

            // Test as guest - should only see the clean comment, no discussion count
            $guestComponent = Livewire::test('comment-component', ['commentable' => $this->mod]);

            // Guests should only see clean comments, but they don't see the main discussion header with count (the
            // discussion header is only shown to authenticated users). Just verify guests don't see non-clean comments.
            $guestComponent->assertDontSee('Pending comment')
                ->assertDontSee('Spam comment');

            // Test as moderator - should see all 4 comments
            Livewire::actingAs($this->moderator)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('(4)');
        });
    });

    describe('nested comments', function (): void {
        it('follows same visibility rules as root comments', function (): void {
            // Create a clean parent comment
            $parentComment = Comment::factory()->for($this->mod, 'commentable')->create([
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Parent comment',
            ]);

            // Create child comments with different statuses
            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->author->id,
                'parent_id' => $parentComment->id,
                'root_id' => $parentComment->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'My pending reply',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
                'parent_id' => $parentComment->id,
                'root_id' => $parentComment->id,
                'spam_status' => SpamStatus::PENDING->value,
                'body' => 'Other pending reply',
            ]);

            Comment::factory()->for($this->mod, 'commentable')->create([
                'parent_id' => $parentComment->id,
                'root_id' => $parentComment->id,
                'spam_status' => SpamStatus::CLEAN->value,
                'body' => 'Clean reply',
            ]);

            // Test as author - should see parent, own pending reply, and clean reply
            Livewire::actingAs($this->author)
                ->test('comment-component', ['commentable' => $this->mod])
                ->assertSee('Parent comment')
                ->assertSee('My pending reply')
                ->assertDontSee('Other pending reply')
                ->assertSee('Clean reply');
        });
    });
});

describe('SpamRibbon', function (): void {
    it('dispatches comment-updated event when marking comment as spam', function (): void {
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('markCommentAsSpam', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: true);

        $comment->refresh();
        expect($comment->isSpam())->toBeTrue();
    });

    it('dispatches comment-updated event when marking comment as ham', function (): void {
        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        // First mark as spam using the model method
        $comment->markAsSpamByModerator($moderator->id);

        $this->actingAs($moderator);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('markCommentAsHam', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: false);

        $comment->refresh();
        expect($comment->isSpamClean())->toBeTrue();
    });

    it('dispatches comment-updated event when soft deleting comment', function (): void {
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('softDeleteComment', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, deleted: true);

        $comment->refresh();
        expect($comment->isDeleted())->toBeTrue();
    });

    it('dispatches comment-updated event when restoring comment', function (): void {
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

        Livewire::test('comment-component', ['commentable' => $mod])
            ->call('restoreComment', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, deleted: false);

        $comment->refresh();
        expect($comment->isDeleted())->toBeFalse();
    });

    it('updates comment status when spam check job completes', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        // Test that the job updates the comment when Akismet is disabled
        Config::set('akismet.enabled', false);

        $job = new CheckCommentForSpam($comment, isRecheck: true);
        $job->handle(resolve(CommentSpamService::class));

        // Verify that the comment was marked as clean
        $comment->refresh();
        expect($comment->isSpamClean())->toBeTrue();
        expect($comment->spam_metadata['reason'])->toBe('akismet_disabled');
        expect($comment->spam_checked_at)->not->toBeNull();
    });

    it('polls for spam check completion and dispatches comment-updated event', function (): void {
        // The on-demand recheck control is only available while Akismet is enabled.
        Config::set('akismet.enabled', true);

        $moderator = createModerator();
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $user->id]);

        $comment = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_checked_at' => now()->subMinute(), // Set initial timestamp
        ]);

        $this->actingAs($moderator);

        // Test the polling mechanism
        $component = Livewire::test('comment-component', ['commentable' => $mod]);

        // Start spam check which should set up polling state
        $component->call('checkCommentForSpam', $comment->id)
            ->assertDispatched('start-spam-check-polling', $comment->id);

        // Verify polling state is set
        expect($component->get('spamCheckStates')[$comment->id]['inProgress'])->toBeTrue();

        // Simulate job completion by updating the comment timestamp
        $comment->update(['spam_checked_at' => now()]);

        // Poll for status - should detect completion and dispatch event
        $component->call('pollSpamCheckStatus', $comment->id)
            ->assertDispatched('comment-updated', $comment->id, spam: false)
            ->assertDispatched('stop-spam-check-polling', $comment->id);

        // Verify polling state is cleared
        expect($component->get('spamCheckStates')[$comment->id]['inProgress'])->toBeFalse();
    });
});

describe('Disabled', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
        Queue::fake(); // Prevent spam check jobs from running

        $this->user = User::factory()->create();
        $this->license = License::factory()->create();
        $this->mod = Mod::factory()->create([
            'owner_id' => $this->user->id,
            'license_id' => $this->license->id,
            'comments_disabled' => false,
            'published_at' => now(),
        ]);
    });

    describe('mod model', function (): void {
        it('has comments_disabled cast as boolean', function (): void {
            expect($this->mod->comments_disabled)->toBeBool();
        });

        it('can receive comments when comments are not disabled', function (): void {
            expect($this->mod->canReceiveComments())->toBeTrue();
        });

        it('cannot receive comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);

            expect($this->mod->canReceiveComments())->toBeFalse();
        });

        it('cannot receive comments when unpublished even if comments enabled', function (): void {
            $this->mod->update(['published_at' => null]);

            expect($this->mod->canReceiveComments())->toBeFalse();
        });
    });

    describe('comment policy with disabled comments', function (): void {
        beforeEach(function (): void {
            $this->otherUser = User::factory()->create();
            $this->moderator = User::factory()->moderator()->create();
            $this->admin = User::factory()->admin()->create();

            $this->comment = Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
            ]);
        });

        it('allows normal users to view comments when comments are not disabled', function (): void {
            expect($this->user->can('view', $this->comment))->toBeTrue();
        });

        it('prevents normal users from viewing comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect($this->otherUser->can('view', $this->comment))->toBeFalse();
        });

        it('allows moderators to view comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect($this->moderator->can('view', $this->comment))->toBeTrue();
        });

        it('allows admins to view comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect($this->admin->can('view', $this->comment))->toBeTrue();
        });

        it('allows mod owners to view comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect($this->user->can('view', $this->comment))->toBeTrue();
        });

        it('allows mod authors to view comments when comments are disabled', function (): void {
            $author = User::factory()->create();
            $this->mod->additionalAuthors()->attach($author);
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect($author->can('view', $this->comment))->toBeTrue();
        });

        it('prevents guests from viewing comments when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->comment->refresh();

            expect(auth()->guest())->toBeTrue();
            expect(policy(Comment::class)->view(null, $this->comment))->toBeFalse();
        });

        it('prevents comment creation when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);

            expect($this->user->can('create', [Comment::class, $this->mod]))->toBeFalse();
        });

        it('allows comment creation when comments are enabled', function (): void {
            expect($this->user->can('create', [Comment::class, $this->mod]))->toBeTrue();
        });
    });

    describe('mod create form', function (): void {
        it('creates mod with comments disabled when checkbox is checked', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
            $user = User::factory()->withMfa()->create();

            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod')
                ->set('guid', 'com.test.mod')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $license->id)
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/mod')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->set('commentsDisabled', true)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'Test Mod')->first();

            expect($mod)->not()->toBeNull();
            expect($mod->comments_disabled)->toBeTrue();
        });

        it('creates mod with comments enabled by default', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
            $user = User::factory()->withMfa()->create();

            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod 2')
                ->set('guid', 'com.test.mod2')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $license->id)
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/mod2')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'Test Mod 2')->first();

            expect($mod)->not()->toBeNull();
            expect($mod->comments_disabled)->toBeFalse();
        });
    });

    describe('mod edit form', function (): void {
        it('updates mod to disable comments', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create([
                'owner_id' => $user->id,
                'license_id' => $license->id,
                'comments_disabled' => false,
                'guid' => 'com.test.editform.disable',
            ]);

            $this->actingAs($user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('commentsDisabled', true)
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->comments_disabled)->toBeTrue();
        });

        it('updates mod to enable comments', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create([
                'owner_id' => $user->id,
                'license_id' => $license->id,
                'comments_disabled' => true,
                'guid' => 'com.test.editform.enable',
            ]);

            $this->actingAs($user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id])
                ->set('commentsDisabled', false)
                ->call('save')
                ->assertHasNoErrors();

            $mod->refresh();
            expect($mod->comments_disabled)->toBeFalse();
        });

        it('prefills comments disabled checkbox correctly', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->user);

            $component = Livewire::test('pages::mod.edit', ['modId' => $this->mod->id]);

            expect($component->get('commentsDisabled'))->toBeTrue();
        });
    });

    describe('mod show page comment visibility', function (): void {
        beforeEach(function (): void {
            $this->otherUser = User::factory()->create();
            $this->moderator = User::factory()->moderator()->create();
            $this->admin = User::factory()->admin()->create();

            $this->comment = Comment::factory()->for($this->mod, 'commentable')->create([
                'user_id' => $this->otherUser->id,
            ]);
        });

        it('shows comments tab for mod owners when comments are enabled', function (): void {
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component');
        });

        it('hides comments tab for normal users when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->otherUser);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSeeText('Comment')
                ->assertDontSee('comment-component');
        });

        it('shows comments tab for moderators when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->moderator);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component');
        });

        it('shows comments tab for admins when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->admin);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component');
        });

        it('shows comments tab for mod owners when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component');
        });

        it('shows comments tab for mod authors when comments are disabled', function (): void {
            $author = User::factory()->create();
            $this->mod->additionalAuthors()->attach($author);
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($author);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component');
        });

        it('shows admin notice when comments are disabled and user is admin', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->admin);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('not visible to normal users');
        });

        it('shows moderator notice when comments are disabled and user is moderator', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->moderator);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('not visible to normal users');
        });

        it('shows owner notice when comments are disabled and user is mod owner', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('not visible to normal users')
                ->assertSee('mod owner or author');
        });

        it('shows author notice when comments are disabled and user is mod author', function (): void {
            $author = User::factory()->create();
            $this->mod->additionalAuthors()->attach($author);
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($author);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('not visible to normal users')
                ->assertSee('mod owner or author');
        });

        it('hides comments for guests when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Comments', false)
                ->assertDontSee('comment-component');
        });

        it('hides comment creation form when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->otherUser);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Post Comment')
                ->assertDontSee('comment-component');
        });

        it('shows comment creation form when comments are enabled', function (): void {
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertSeeText('Comment')
                ->assertSee('comment-component')
                ->assertDontSee('Comments have been disabled for this mod');
        });

        it('hides comment creation form for mod owners when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Post Comment')
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('Comments are disabled.');
        });

        it('hides comment creation form for mod authors when comments are disabled', function (): void {
            $author = User::factory()->create();
            $this->mod->additionalAuthors()->attach($author);
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($author);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Post Comment')
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('Comments are disabled.');
        });

        it('hides comment creation form for admins when comments are disabled', function (): void {
            $this->mod->update(['comments_disabled' => true]);
            $this->actingAs($this->admin);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Post Comment')
                ->assertSee('Comments have been disabled for this mod')
                ->assertSee('Comments are disabled.');
        });

        it('does not show comment enable/disable options in action menu', function (): void {
            $this->actingAs($this->user);

            Livewire::withoutLazyLoading()
                ->test('pages::mod.show', ['modId' => $this->mod->id, 'slug' => $this->mod->slug])
                ->assertDontSee('Enable Comments')
                ->assertDontSee('Disable Comments');
        });
    });
});

describe('Translation', function (): void {
    it('shows the translation block for a translated comment', function (): void {
        $mod = createPublishedMod();
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'body' => 'Привет, отличный мод! Спасибо за вашу работу.',
        ]);
        $comment->latestVersion?->applyTranslationResult(new CommentTranslationResult(
            detectedLanguage: 'ru',
            translatedBody: 'Hello, great mod! Thank you for your work.',
            metadata: ['provider' => 'anthropic'],
        ));

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('Machine translated from Russian')
            ->assertSee('Hello, great mod! Thank you for your work.');
    });

    it('does not show a translation block for untranslated comments', function (): void {
        $mod = createPublishedMod();
        Comment::factory()->for($mod, 'commentable')->create([
            'body' => 'This is a regular English comment for the test.',
        ]);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertSee('This is a regular English comment for the test.')
            ->assertDontSee('Machine translated from');
    });

    it('queues a translation when a comment is edited', function (): void {
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

        Config::set('comments.translation.enabled', true);
        Bus::fake([TranslateComment::class]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.edit-'.$comment->id.'.body', 'Ce mod est vraiment excellent, merci beaucoup pour votre travail!')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        Bus::assertDispatched(TranslateComment::class, fn (TranslateComment $job): bool => $job->comment->id === $comment->id);
    });
});

describe('blocked user comment visibility', function (): void {
    it('collapses comments from users the viewer has blocked', function (): void {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $author->id,
        ]);

        $viewer->block($author);

        Livewire::actingAs($viewer)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSeeHtml('data-test="blocked-comment-'.$comment->id.'"')
            ->assertSee('This comment is from a user you have blocked.');
    });

    it('shows comments normally when there is no block relationship', function (): void {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $author->id,
        ]);

        Livewire::actingAs($viewer)
            ->test('comment-component', ['commentable' => $mod])
            ->assertDontSeeHtml('data-test="blocked-comment-'.$comment->id.'"');
    });

    it('does not collapse comments from users who have blocked the viewer', function (): void {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $author->id,
        ]);

        $author->block($viewer);

        Livewire::actingAs($viewer)
            ->test('comment-component', ['commentable' => $mod])
            ->assertDontSeeHtml('data-test="blocked-comment-'.$comment->id.'"');
    });

    it('does not collapse comments for guests', function (): void {
        $author = User::factory()->create();
        $blocker = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $author->id,
        ]);

        $blocker->block($author);

        Livewire::test('comment-component', ['commentable' => $mod])
            ->assertDontSeeHtml('data-test="blocked-comment-'.$comment->id.'"');
    });

    it('collapses replies from users the viewer has blocked', function (): void {
        $viewer = User::factory()->create();
        $rootAuthor = User::factory()->create();
        $replyAuthor = User::factory()->create();
        $mod = Mod::factory()->create();
        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $rootAuthor->id,
        ]);
        $reply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $replyAuthor->id,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $viewer->block($replyAuthor);

        Livewire::actingAs($viewer)
            ->test('comment-component', ['commentable' => $mod])
            ->assertDontSeeHtml('data-test="blocked-comment-'.$rootComment->id.'"')
            ->assertSeeHtml('data-test="blocked-comment-'.$reply->id.'"');
    });
});
