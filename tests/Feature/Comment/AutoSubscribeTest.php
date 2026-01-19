<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('Auto-subscribe on comment', function (): void {
    it('automatically subscribes user when they create a root comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Initially not subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        // Create a comment via Livewire component
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'This is my first comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Should now be subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        // Verify comment was created
        $comment = Comment::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('This is my first comment');
    });

    it('automatically subscribes user when they reply to a comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $otherUser = User::factory()->create();

        // Create a comment from another user
        $parentComment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Initially not subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        // Reply to the comment via Livewire component
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.reply-'.$parentComment->id.'.body', 'This is my reply')
            ->set('formStates.reply-'.$parentComment->id.'.visible', true)
            ->call('createReply', $parentComment->id)
            ->assertHasNoErrors();

        // Should now be subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        // Verify reply was created
        $reply = Comment::query()
            ->where('user_id', $user->id)
            ->where('parent_id', $parentComment->id)
            ->first();

        expect($reply)->not->toBeNull()
            ->and($reply->body)->toBe('This is my reply');
    });

    it('does not duplicate subscription if user is already subscribed', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Subscribe the user first
        CommentSubscription::subscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        // Count subscriptions before
        $subscriptionCountBefore = CommentSubscription::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->count();

        expect($subscriptionCountBefore)->toBe(1);

        // Create a comment
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Another comment')
            ->call('createComment')
            ->assertHasNoErrors();

        // Should still be subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        // Count should remain the same (no duplicates)
        $subscriptionCountAfter = CommentSubscription::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->count();

        expect($subscriptionCountAfter)->toBe(1);
    });

    it('maintains subscription state in component after auto-subscribe', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Initially not subscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        // Create a comment and check the subscription state
        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSet('isSubscribed', false)
            ->set('newCommentBody', 'Test comment')
            ->call('createComment')
            ->assertHasNoErrors()
            ->assertSet('isSubscribed', true);  // Component state should be updated

        // Should now be subscribed in database
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();
    });

    it('resubscribes user who previously unsubscribed when they comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        // Subscribe then unsubscribe
        CommentSubscription::subscribe($user, $mod);
        CommentSubscription::unsubscribe($user, $mod);

        // Verify unsubscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        // Create a comment
        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', "I'm back!")
            ->call('createComment')
            ->assertHasNoErrors();

        // Should be resubscribed
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();
    });

    it('subscribes user when commenting on user profile', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        // Initially not subscribed
        expect(CommentSubscription::isSubscribed($commenter, $profileUser))->toBeFalse();

        // Create a comment on the user profile
        Livewire::actingAs($commenter)
            ->test('comment-component', ['commentable' => $profileUser])
            ->set('newCommentBody', 'Nice profile!')
            ->call('createComment')
            ->assertHasNoErrors();

        // Should now be subscribed
        expect(CommentSubscription::isSubscribed($commenter, $profileUser))->toBeTrue();

        // Verify comment was created
        $comment = Comment::query()
            ->where('user_id', $commenter->id)
            ->where('commentable_id', $profileUser->id)
            ->where('commentable_type', User::class)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('Nice profile!');
    });
});
