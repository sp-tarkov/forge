<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('guest restrictions', function (): void {
    it('should not show reply button to guests', function (): void {
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'body' => 'Test comment',
        ]);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('Test comment')
            ->assertDontSee('Reply');
    });

    it('should not allow a guest to reply to a comment', function (): void {
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
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
            ->test(CommentComponent::class, ['commentable' => $mod])
            ->set('formStates.reply-'.$parentComment->id.'.body', 'This is a reply.')
            ->call('createReply', $parentComment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a reply.',
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'parent_id' => $parentComment->id,
        ]);
    });
});

describe('reply validation', function (): void {
    it('should validate parent comment exists when replying', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $nonExistentCommentId = 99999;

        Livewire::actingAs($user)
            ->test(CommentComponent::class, ['commentable' => $mod])
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
            ->test(CommentComponent::class, ['commentable' => $mod2])
            ->set('formStates.reply-'.$comment->id.'.body', 'Cross-mod reply attempt')
            ->call('createReply', $comment->id)
            ->assertNotFound(); // Returns 404 because comment not found in mod2
    });
});

describe('comment hierarchy', function (): void {
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
            ->test(CommentComponent::class, ['commentable' => $mod])
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
            ->test(CommentComponent::class, ['commentable' => $mod])
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
            ->test(CommentComponent::class, ['commentable' => $mod]);

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
        $adminRole = UserRole::factory()->administrator()->create();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CommentComponent::class, ['commentable' => $mod]);

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
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
        ]);

        $component = Livewire::actingAs($moderator)
            ->test(CommentComponent::class, ['commentable' => $mod]);

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
            ->test(CommentComponent::class, ['commentable' => $profileOwner])
            ->set('formStates.reply-'.$comment->id.'.body', 'I agree!')
            ->call('createReply', $comment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'I agree!',
            'user_id' => $replier->id,
            'commentable_id' => $profileOwner->id,
            'commentable_type' => User::class,
            'parent_id' => $comment->id,
        ]);
    });
});
