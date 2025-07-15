<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Single component for managing all comment functionality.
 */
class CommentComponent extends Component
{
    use WithPagination;

    /**
     * The commentable model.
     */
    public Mod $commentable;

    /**
     * Body for new root comment.
     */
    public string $newCommentBody = '';

    /**
     * Reply bodies indexed by comment ID.
     *
     * @var array<string, string>
     */
    public array $replyBodies = [];

    /**
     * Edit bodies indexed by comment ID.
     *
     * @var array<string, string>
     */
    public array $editBodies = [];

    /**
     * Show reply form flags indexed by comment ID.
     *
     * @var array<int, bool>
     */
    public array $showReplyForm = [];

    /**
     * Show edit form flags indexed by comment ID.
     *
     * @var array<int, bool>
     */
    public array $showEditForm = [];

    /**
     * Show replies flags indexed by comment ID.
     *
     * @var array<int, bool>
     */
    public array $showReplies = [];

    /**
     * Total comment count.
     */
    public int $commentCount = 0;

    /**
     * User reactions cache.
     *
     * @var array<int>
     */
    public array $userReactions = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->refreshData();
    }

    /**
     * Refresh all component data.
     */
    protected function refreshData(): void
    {
        $this->commentCount = $this->commentable->comments()->count();

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $this->userReactions = $user->commentReactions()
                ->whereIn('comment_id', $this->commentable->comments()->pluck('id'))
                ->pluck('comment_id')
                ->toArray();
        }

        // Initialize show replies for root comments with descendants
        $rootComments = $this->commentable->rootComments()
            ->has('descendants')
            ->pluck('id')
            ->toArray();

        foreach ($rootComments as $commentId) {
            if (! isset($this->showReplies[$commentId])) {
                $this->showReplies[$commentId] = true;
            }
        }
    }

    /**
     * Create a new root comment.
     */
    public function createComment(): void
    {
        $this->authorize('create', [Comment::class, $this->commentable]);

        // Rate limiting: 1 comment per 30 seconds per user
        $key = 'comment-creation:'.Auth::id();

        abort_if(RateLimiter::tooManyAttempts($key, 1), 403, 'Too many comment attempts. Please wait before commenting again.');

        $this->validate([
            'newCommentBody' => 'required|string|min:3|max:10000',
        ]);

        $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'body' => $this->newCommentBody,
        ]);

        // Apply rate limit after successful comment creation
        RateLimiter::hit($key, 30); // 30 seconds

        $this->newCommentBody = '';
        $this->refreshData();
    }

    /**
     * Create a reply to a comment.
     */
    public function createReply(int $parentId): void
    {
        $this->authorize('create', [Comment::class, $this->commentable]);

        // Rate limiting: 1 comment per 30 seconds per user (same as root comments)
        $key = 'comment-creation:'.Auth::id();

        abort_if(RateLimiter::tooManyAttempts($key, 1), 403, 'Too many comment attempts. Please wait before commenting again.');

        // Validate parent comment exists and belongs to this commentable
        $parentComment = Comment::query()->where('id', $parentId)
            ->where('commentable_id', $this->commentable->id)
            ->where('commentable_type', $this->commentable::class)
            ->first();

        abort_unless($parentComment !== null, 404, 'Parent comment not found');

        $bodyKey = 'comment-'.$parentId;

        $this->validate([
            'replyBodies.'.$bodyKey => 'required|string|min:3|max:10000',
        ], [], [
            'replyBodies.'.$bodyKey => 'reply',
        ]);

        $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'body' => $this->replyBodies[$bodyKey],
            'parent_id' => $parentId,
        ]);

        // Apply rate limit after successful reply creation
        RateLimiter::hit($key, 30); // 30 seconds

        unset($this->replyBodies[$bodyKey]);
        unset($this->showReplyForm[$parentId]);

        $this->refreshData();
    }

    /**
     * Update an existing comment.
     */
    public function updateComment(Comment $comment): void
    {
        // Validate comment belongs to this commentable
        abort_if($comment->commentable_id !== $this->commentable->id ||
            $comment->commentable_type !== $this->commentable::class, 403, 'Cannot edit comment from different page');

        $this->authorize('update', $comment);

        $bodyKey = 'comment-'.$comment->id;

        $this->validate([
            'editBodies.'.$bodyKey => 'required|string|min:3|max:10000',
        ], [], [
            'editBodies.'.$bodyKey => 'comment',
        ]);

        $comment->update([
            'body' => $this->editBodies[$bodyKey],
            'edited_at' => now(),
        ]);

        unset($this->editBodies[$bodyKey]);
        unset($this->showEditForm[$comment->id]);
    }

    /**
     * Toggle reaction on a comment.
     */
    public function toggleReaction(Comment $comment): void
    {
        // Validate comment belongs to this commentable
        abort_if($comment->commentable_id !== $this->commentable->id ||
            $comment->commentable_type !== $this->commentable::class, 403, 'Cannot react to comment from different page');

        $this->authorize('react', $comment);

        /** @var User $user */
        $user = Auth::user();

        /** @var ?CommentReaction $reaction */
        $reaction = $user->commentReactions()
            ->where('comment_id', $comment->id)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $this->userReactions = array_diff($this->userReactions, [$comment->id]);
        } else {
            $user->commentReactions()->create(['comment_id' => $comment->id]);
            $this->userReactions[] = $comment->id;
        }
    }

    /**
     * Toggle a reply form for a comment.
     */
    public function toggleReplyForm(int $commentId): void
    {
        $this->showReplyForm[$commentId] = ! ($this->showReplyForm[$commentId] ?? false);

        // Close edit form if open
        if ($this->showReplyForm[$commentId]) {
            unset($this->showEditForm[$commentId]);
            unset($this->editBodies['comment-'.$commentId]);
        }

        // Initialize reply body if showing form
        if ($this->showReplyForm[$commentId] && ! isset($this->replyBodies['comment-'.$commentId])) {
            $this->replyBodies['comment-'.$commentId] = '';
        }
    }

    /**
     * Toggle an edit form for a comment.
     */
    public function toggleEditForm(Comment $comment): void
    {
        $this->showEditForm[$comment->id] = ! ($this->showEditForm[$comment->id] ?? false);

        // Close the reply form if open
        if ($this->showEditForm[$comment->id]) {
            unset($this->showReplyForm[$comment->id]);
            unset($this->replyBodies['comment-'.$comment->id]);

            // Initialize edit body with current comment
            $this->editBodies['comment-'.$comment->id] = $comment->body;
        } else {
            unset($this->editBodies['comment-'.$comment->id]);
        }
    }

    /**
     * Toggle replies to visibility for a comment.
     */
    public function toggleReplies(int $commentId): void
    {
        $this->showReplies[$commentId] = ! ($this->showReplies[$commentId] ?? true);
    }

    /**
     * Check if a user has reacted to a comment.
     */
    public function hasReacted(int $commentId): bool
    {
        return in_array($commentId, $this->userReactions);
    }

    /**
     * Check if a comment can be edited (within 5 minutes).
     */
    public function canEditComment(Comment $comment): bool
    {
        if (! Auth::check() || $comment->user_id !== Auth::id()) {
            return false;
        }

        return $comment->created_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $rootComments = $this->commentable
            ->rootComments()
            ->with(['user', 'descendants', 'descendants.user', 'descendants.reactions', 'reactions'])
            ->latest()
            ->paginate(perPage: 10, pageName: 'commentPage');

        return view('livewire.comment.manager', [
            'rootComments' => $rootComments,
        ]);
    }
}
