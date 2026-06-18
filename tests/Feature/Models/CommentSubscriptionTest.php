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

describe('subscribe and unsubscribe', function (): void {
    it('allows users to subscribe and unsubscribe from a commentable', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        CommentSubscription::subscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        CommentSubscription::unsubscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();
    });
});

describe('no auto-subscribe on comment', function (): void {
    it('does not auto-subscribe user when they create a root comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'This is my first comment')
            ->call('createComment')
            ->assertHasNoErrors();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        $comment = Comment::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('This is my first comment');
    });

    it('does not auto-subscribe user when they reply to a comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $otherUser = User::factory()->create();

        $parentComment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('formStates.reply-'.$parentComment->id.'.body', 'This is my reply')
            ->set('formStates.reply-'.$parentComment->id.'.visible', true)
            ->call('createReply', $parentComment->id)
            ->assertHasNoErrors();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        $reply = Comment::query()
            ->where('user_id', $user->id)
            ->where('parent_id', $parentComment->id)
            ->first();

        expect($reply)->not->toBeNull()
            ->and($reply->body)->toBe('This is my reply');
    });

    it('maintains existing subscription if user is already subscribed', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        CommentSubscription::subscribe($user, $mod);
        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        $subscriptionCountBefore = CommentSubscription::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->count();

        expect($subscriptionCountBefore)->toBe(1);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Another comment')
            ->call('createComment')
            ->assertHasNoErrors();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeTrue();

        $subscriptionCountAfter = CommentSubscription::query()
            ->where('user_id', $user->id)
            ->where('commentable_id', $mod->id)
            ->where('commentable_type', Mod::class)
            ->count();

        expect($subscriptionCountAfter)->toBe(1);
    });

    it('maintains subscription state in component correctly', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSet('isSubscribed', false)
            ->set('newCommentBody', 'Test comment')
            ->call('createComment')
            ->assertHasNoErrors()
            ->assertSet('isSubscribed', false);

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();
    });

    it('does not resubscribe user who previously unsubscribed when they comment', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        CommentSubscription::subscribe($user, $mod);
        CommentSubscription::unsubscribe($user, $mod);

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', "I'm back!")
            ->call('createComment')
            ->assertHasNoErrors();

        expect(CommentSubscription::isSubscribed($user, $mod))->toBeFalse();
    });

    it('does not subscribe user when commenting on a user profile', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        expect(CommentSubscription::isSubscribed($commenter, $profileUser))->toBeFalse();

        Livewire::actingAs($commenter)
            ->test('comment-component', ['commentable' => $profileUser])
            ->set('newCommentBody', 'Nice profile!')
            ->call('createComment')
            ->assertHasNoErrors();

        expect(CommentSubscription::isSubscribed($commenter, $profileUser))->toBeFalse();

        $comment = Comment::query()
            ->where('user_id', $commenter->id)
            ->where('commentable_id', $profileUser->id)
            ->where('commentable_type', User::class)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('Nice profile!');
    });
});
