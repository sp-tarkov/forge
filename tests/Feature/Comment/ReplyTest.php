<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

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
        ->set('replyBodies.comment-'.$parentComment->id, 'This is a reply.')
        ->call('createReply', $parentComment->id)
        ->assertForbidden();
});

it('should allow a user to reply to a comment', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $parentComment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);

    Livewire::actingAs($user)
        ->test(CommentComponent::class, ['commentable' => $mod])
        ->set('replyBodies.comment-'.$parentComment->id, 'This is a reply.')
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

it('should validate parent comment exists when replying', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $nonExistentCommentId = 99999;

    Livewire::actingAs($user)
        ->test(CommentComponent::class, ['commentable' => $mod])
        ->set('replyBodies.comment-'.$nonExistentCommentId, 'Reply to non-existent comment')
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
        ->set('replyBodies.comment-'.$comment->id, 'Cross-mod reply attempt')
        ->call('createReply', $comment->id)
        ->assertNotFound(); // Returns 404 because comment not found in mod2
});

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
        ->set('replyBodies.comment-'.$rootComment->id, 'Valid reply')
        ->call('createReply', $rootComment->id)
        ->assertHasNoErrors();

    $reply = Comment::query()->where('parent_id', $rootComment->id)->first();
    expect($reply)->not->toBeNull()
        ->and($reply->parent_id)->toBe($rootComment->id);

    // Can also reply to replies (nested comments are allowed but displayed flat)
    Livewire::actingAs($user)
        ->test(CommentComponent::class, ['commentable' => $mod])
        ->set('replyBodies.comment-'.$reply->id, 'Reply to reply')
        ->call('createReply', $reply->id)
        ->assertHasNoErrors();
});

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
    $component->set('replyBodies.comment-'.$parentComment->id, 'First reply')
        ->call('createReply', $parentComment->id)
        ->assertHasNoErrors();

    // The second reply immediately after should be rate limited
    $component->set('replyBodies.comment-'.$parentComment->id, 'Second reply')
        ->call('createReply', $parentComment->id)
        ->assertForbidden();

    // Verify only one reply was created
    $replies = Comment::query()->where('user_id', $user->id)
        ->where('parent_id', $parentComment->id)
        ->count();

    expect($replies)->toBe(1);
});

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
        ->set('replyBodies.comment-'.$comment->id, 'I agree!')
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
