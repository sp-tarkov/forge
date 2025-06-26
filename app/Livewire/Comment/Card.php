<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Card extends Component
{
    public Comment $comment;

    /**
     * Executed when the component is first loaded.
     */
    public function mount(): void {}

    /**
     * Check if the current user has reacted to this comment.
     */
    #[Computed]
    public function hasReacted(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->commentReactions()
            ->where('comment_id', $this->comment->id)
            ->exists();
    }

    /**
     * When a user "hearts" a comment.
     */
    public function react(Comment $comment): void
    {
        $this->authorize('react', $comment);

        /** @var User $user */
        $user = Auth::user(); // Not null, verified by policy.

        /** @var ?CommentReaction $reaction */
        $reaction = $user->commentReactions()->where('comment_id', $comment->id)->first();

        if ($reaction) {
            $reaction->delete();
        } else {
            $user->commentReactions()->save(new CommentReaction(['comment_id' => $comment->id]));
        }

        // Bust the hasReacted cache.
        unset($this->hasReacted);

        $this->dispatch(
            'comment-reaction.'.$comment->id,
            status: $reaction ? 'deleted' : 'created',
        );
    }

    /**
     * Listen for comment updates and refresh this comment if it matches.
     */
    #[On('comment-updated')]
    public function commentUpdated(int $commentId): void
    {
        if ($this->comment->id === $commentId) {
            $this->comment->refresh();
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.comment.card');
    }
}
