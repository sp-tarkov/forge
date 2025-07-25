<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

describe('reaction permissions', function (): void {
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

        Livewire::test(CommentComponent::class, ['commentable' => $mod])
            ->assertSee('Test comment')
            ->assertSee('3 Likes')
            ->assertDontSee('wire:click="toggleReaction"', false);
    });
});

describe('reaction toggling', function (): void {
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
});
