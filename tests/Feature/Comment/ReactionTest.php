<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

it('should not allow users to react to their own comments', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);

    Livewire::actingAs($user)
        ->test(CommentComponent::class, ['commentable' => $mod])
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

    Livewire::test(CommentComponent::class, ['commentable' => $mod])
        ->call('toggleReaction', $comment)
        ->assertForbidden();
});

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
        ->test(CommentComponent::class, ['commentable' => $mod]);

    // Add reaction
    $component->call('toggleReaction', $comment)
        ->assertHasNoErrors();

    expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeTrue()
        ->and($component->get('userReactions'))->toContain($comment->id);

    // Remove reaction
    $component->call('toggleReaction', $comment)
        ->assertHasNoErrors();

    expect($user->commentReactions()->where('comment_id', $comment->id)->exists())->toBeFalse()
        ->and($component->get('userReactions'))->not->toContain($comment->id);
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
        ->test(CommentComponent::class, ['commentable' => $mod]);

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
