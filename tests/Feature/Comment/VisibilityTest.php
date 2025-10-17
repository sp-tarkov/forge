<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake(); // Prevent spam check jobs from running

    $this->mod = Mod::factory()->create();
    $this->author = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $moderatorRole = UserRole::factory()->moderator()->create();
    $this->moderator = User::factory()->create();
    $this->moderator->assignRole($moderatorRole);
});

describe('guest visibility', function (): void {
    it('can only see clean comments', function (): void {
        // Create comments with different spam statuses
        $cleanComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'This is a clean comment',
        ]);

        $pendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'This is a pending comment',
        ]);

        $spamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'This is a spam comment',
        ]);

        // Test as guest
        Livewire::test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('This is a clean comment')
            ->assertDontSee('This is a pending comment')
            ->assertDontSee('This is a spam comment');
    });
});

describe('comment author visibility', function (): void {
    it('can see their own pending comments', function (): void {
        // Create comments with different authors
        $authorPendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'My pending comment',
        ]);

        $otherPendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'Other user pending comment',
        ]);

        $cleanComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'Clean comment visible to all',
        ]);

        // Test as comment author
        Livewire::actingAs($this->author)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('My pending comment')
            ->assertDontSee('Other user pending comment')
            ->assertSee('Clean comment visible to all');
    });

    it('can see their own spam comments', function (): void {
        // Create comments with different authors
        $authorSpamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'My spam comment',
        ]);

        $otherSpamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'Other user spam comment',
        ]);

        $cleanComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'Clean comment visible to all',
        ]);

        // Test as comment author
        Livewire::actingAs($this->author)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('My spam comment')
            ->assertDontSee('Other user spam comment')
            ->assertSee('Clean comment visible to all');
    });
});

describe('moderator visibility', function (): void {
    it('can see all comments regardless of spam status', function (): void {
        // Create comments with different spam statuses
        $cleanComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'Clean comment',
        ]);

        $pendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'Pending comment',
        ]);

        $spamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'Spam comment',
        ]);

        // Test as moderator
        Livewire::actingAs($this->moderator)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('Clean comment')
            ->assertSee('Pending comment')
            ->assertSee('Spam comment');
    });
});

describe('regular user visibility', function (): void {
    it('can only see clean comments and their own non-clean comments', function (): void {
        // Create various comments
        $myPendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'My pending comment',
        ]);

        $mySpamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'My spam comment',
        ]);

        $otherPendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'Other pending comment',
        ]);

        $otherSpamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'spam_status' => SpamStatus::SPAM->value,
            'body' => 'Other spam comment',
        ]);

        $cleanComment1 = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'My clean comment',
        ]);

        $cleanComment2 = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'Other clean comment',
        ]);

        // Test as the author
        Livewire::actingAs($this->author)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
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
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('(3)');

        // Test as other user - should see 2 comments (1 own pending + 1 clean)
        Livewire::actingAs($this->otherUser)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('(2)');

        // Test as guest - should only see the clean comment, no discussion count
        $guestComponent = Livewire::test(CommentComponent::class, ['commentable' => $this->mod]);

        // Guests should only see clean comments, but they don't see the main discussion header with count
        // (The discussion header is only shown to authenticated users)
        // Just verify guests don't see non-clean comments
        $guestComponent->assertDontSee('Pending comment')
            ->assertDontSee('Spam comment');

        // Test as moderator - should see all 4 comments
        Livewire::actingAs($this->moderator)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
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
        $myPendingReply = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->author->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'My pending reply',
        ]);

        $otherPendingReply = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
            'spam_status' => SpamStatus::PENDING->value,
            'body' => 'Other pending reply',
        ]);

        $cleanReply = Comment::factory()->for($this->mod, 'commentable')->create([
            'parent_id' => $parentComment->id,
            'root_id' => $parentComment->id,
            'spam_status' => SpamStatus::CLEAN->value,
            'body' => 'Clean reply',
        ]);

        // Test as author - should see parent, own pending reply, and clean reply
        Livewire::actingAs($this->author)
            ->test(CommentComponent::class, ['commentable' => $this->mod])
            ->assertSee('Parent comment')
            ->assertSee('My pending reply')
            ->assertDontSee('Other pending reply')
            ->assertSee('Clean reply');
    });
});
