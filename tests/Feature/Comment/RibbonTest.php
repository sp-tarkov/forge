<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Livewire\Ribbon\Comment as CommentRibbon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake(); // Prevent spam check jobs from running

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $this->moderator = User::factory()->moderator()->create();

    // Create admin with proper role
    $this->admin = User::factory()->admin()->create();

    $this->mod = Mod::factory()->create();
});

/**
 * Helper function to create component props
 *
 * @return array<string, mixed>
 */
function getCommentRibbonProps(Comment $comment, ?User $actingAs = null): array
{
    return [
        'commentId' => $comment->id,
        'spamStatus' => $comment->spam_status->value,
        'canSeeRibbon' => $actingAs?->can('seeRibbon', $comment) ?? false,
    ];
}

describe('Comment Spam Status Ribbons', function (): void {
    it('shows no ribbon for clean comments', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CommentRibbon::class, [
                'commentId' => $comment->id,
                'spamStatus' => $comment->spam_status->value,
                'canSeeRibbon' => $this->user->can('seeRibbon', $comment),
            ])
            ->assertDontSee('class="ribbon');
    });

    it('hides pending ribbon from comment author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->user))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Pending');
    });

    it('hides pending ribbon from other users', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->otherUser)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->otherUser))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Pending');
    });

    it('shows pending ribbon to moderators who are not comment authors', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->moderator)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->moderator))
            ->assertSee('ribbon yellow')
            ->assertSee('Pending');
    });

    it('hides pending ribbon from comment author even if they are admin', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->admin))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Pending');
    });

    it('hides pending ribbon from comment author even if they are moderator', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->moderator->id,
        ]);

        Livewire::actingAs($this->moderator)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->moderator))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Pending');
    });

    it('hides spam ribbon from comment author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->user))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Spam');
    });

    it('hides spam ribbon from other users', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->otherUser)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->otherUser))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Spam');
    });

    it('shows spam ribbon to moderators', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->moderator)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->moderator))
            ->assertSee('ribbon red')
            ->assertSee('Spam');
    });

    it('shows spam ribbon to admins', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CommentRibbon::class, getCommentRibbonProps($comment, $this->admin))
            ->assertSee('ribbon red')
            ->assertSee('Spam');
    });

    it('shows no ribbon to guests for any comment status', function (): void {
        $pendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
        ]);

        $spamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
        ]);

        Livewire::test(CommentRibbon::class, getCommentRibbonProps($pendingComment, null))
            ->assertDontSee('class="ribbon');

        Livewire::test(CommentRibbon::class, getCommentRibbonProps($spamComment, null))
            ->assertDontSee('class="ribbon');
    });

    it('uses correct colors for different spam statuses', function (): void {
        $pendingComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        $spamComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        // Test pending comment ribbon color (yellow)
        Livewire::actingAs($this->moderator)
            ->test(CommentRibbon::class, getCommentRibbonProps($pendingComment, $this->moderator))
            ->assertSee('ribbon yellow')
            ->assertSee('Pending');

        // Test spam comment ribbon color (red)
        Livewire::actingAs($this->moderator)
            ->test(CommentRibbon::class, getCommentRibbonProps($spamComment, $this->moderator))
            ->assertSee('ribbon red')
            ->assertSee('Spam');
    });
});
