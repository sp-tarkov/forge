<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Commentable;
use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * Single component for managing all comment functionality.
 */
class CommentComponent extends Component
{
    use UsesSpamProtection;
    use WithPagination;

    /**
     * The commentable model.
     *
     * @var Commentable<Mod|User>
     */
    public Commentable $commentable;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * Body for new root comment.
     */
    public string $newCommentBody = '';

    /**
     * Form states for reply and edit forms indexed by comment ID and type.
     *
     * @var array<string, array{body: string, visible: bool}>
     */
    public array $formStates = [];

    /**
     * Descendant flags indexed by comment ID.
     *
     * @var array<int, bool>
     */
    public array $showDescendants = [];

    /**
     * Loaded descendants indexed by comment ID.
     *
     * @var array<int, Collection<int, Comment>>
     */
    public array $loadedDescendants = [];

    /**
     * Descendant counts indexed by comment ID.
     *
     * @var array<int, int>
     */
    public array $descendantCounts = [];

    /**
     * Whether the current user is subscribed to notifications.
     */
    public bool $isSubscribed = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;
        $this->initializeSubscriptionStatus();
        $this->initializeDescendantCounts();
    }

    /**
     * Get the total comment count.
     */
    #[Computed]
    public function commentCount(): int
    {
        $user = Auth::user();
        $query = $this->commentable->comments();

        if ($user === null) {
            return $query->clean()->count();
        }

        if ($user->isModOrAdmin()) {
            return $query->count();
        }

        // Normal authenticated users see clean comments and their own comments.
        return $query->where(function ($q) use ($user): void {
            $q->clean()->orWhere('user_id', $user->id);
        })->count();
    }

    /**
     * Get user reactions for visible comments.
     *
     * @return array<int>
     */
    #[Computed]
    public function userReactionIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $user = Auth::user();

        // Get reactions for comments that will actually be displayed.
        $reactionQuery = $user->commentReactions()
            ->join('comments', 'comment_reactions.comment_id', '=', 'comments.id')
            ->where('comments.commentable_type', $this->commentable::class)
            ->where('comments.commentable_id', $this->getCommentableId());

        // Normal authenticated users see reactions for clean comments and their own comments.
        if (! $user->isModOrAdmin()) {
            $reactionQuery->where(function ($q) use ($user): void {
                $q->where('comments.spam_status', SpamStatus::CLEAN->value)
                    ->orWhere('comments.user_id', $user->id);
            });
        }

        return $reactionQuery->pluck('comment_reactions.comment_id')->toArray();
    }

    /**
     * Create a new root comment.
     */
    public function createComment(): void
    {
        $this->authorize('create', [Comment::class, $this->commentable]);
        $this->protectAgainstSpam();

        if (! $this->checkRateLimit('newCommentBody')) {
            return;
        }

        $this->validateComment('newCommentBody');
        $this->storeComment($this->newCommentBody);

        $this->applyRateLimit();

        $this->newCommentBody = '';

        // Clear cached computed properties.
        unset($this->commentCount);

        $this->dispatch('$refresh');
    }

    /**
     * Create a reply to a comment.
     */
    public function createReply(int $parentId): void
    {
        $this->authorize('create', [Comment::class, $this->commentable]);
        $this->protectAgainstSpam();

        $formKey = $this->getFormKey('reply', $parentId);
        $fieldKey = sprintf('formStates.%s.body', $formKey);

        if (! $this->checkRateLimit($fieldKey)) {
            return;
        }

        // Validate parent comment exists and belongs to this commentable.
        $this->validateParentComment($parentId);

        $body = $this->formStates[$formKey]['body'] ?? '';
        $this->validateComment($fieldKey, 'reply');
        $this->storeComment($body, $parentId);

        $this->applyRateLimit();

        $this->hideForm('reply', $parentId);

        // Update reply counts and clear loaded replies for the parent.
        $parentComment = Comment::query()->find($parentId);
        if ($parentComment && $parentComment->isRoot()) {
            $rootId = $parentComment->id;
        } else {
            $rootId = $parentComment->root_id;
        }

        if ($rootId) {
            $this->descendantCounts[$rootId] = ($this->descendantCounts[$rootId] ?? 0) + 1;

            // Clear the loaded descendants to force reload when toggled.
            unset($this->loadedDescendants[$rootId]);

            // Automatically show descendants after creating a new reply.
            $this->showDescendants[$rootId] = true;
            $this->loadDescendants($rootId);
        }

        // Clear cached computed properties.
        unset($this->commentCount);

        $this->dispatch('$refresh');
    }

    /**
     * Update an existing comment.
     */
    public function updateComment(Comment $comment): void
    {
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('update', $comment);
        $this->protectAgainstSpam();

        $formKey = $this->getFormKey('edit', $comment->id);
        $fieldKey = 'formStates.'.$formKey.'.body';
        $body = $this->formStates[$formKey]['body'] ?? '';

        $this->validateComment($fieldKey);

        $comment->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        $this->hideForm('edit', $comment->id);
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(Comment $comment): void
    {
        $this->authorize('delete', $comment);

        $editTimeLimit = config('comments.editing.edit_time_limit_minutes', 5);
        $isWithinTimeLimit = $comment->created_at->diffInMinutes(now()) <= $editTimeLimit;
        $hasNoChildren = ! $comment->descendants()->exists();

        if ($isWithinTimeLimit && $hasNoChildren) {
            // Update reply counts if this is a reply being permanently deleted
            if (! $comment->isRoot()) {
                $rootId = $comment->root_id;
                if ($rootId && isset($this->descendantCounts[$rootId])) {
                    $this->descendantCounts[$rootId] = max(0, $this->descendantCounts[$rootId] - 1);
                    // Clear loaded descendants to force reload
                    unset($this->loadedDescendants[$rootId]);
                }
            }

            $comment->delete();
        } else {
            $comment->update(['deleted_at' => now()]);
        }

        // Clear cached computed properties.
        unset($this->commentCount);

        $this->dispatch('$refresh');
    }

    /**
     * Toggle reaction on a comment.
     */
    public function toggleReaction(Comment $comment): void
    {
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('react', $comment);

        /** @var User $user */
        $user = Auth::user();

        /** @var ?CommentReaction $reaction */
        $reaction = $user->commentReactions()
            ->where('comment_id', $comment->id)
            ->first();

        if ($reaction) {
            $reaction->delete();
        } else {
            $user->commentReactions()->create(['comment_id' => $comment->id]);
        }
    }

    /**
     * Pin a comment.
     */
    public function pinComment(Comment $comment): void
    {
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => now()]);

        $this->dispatch('$refresh');
    }

    /**
     * Unpin a comment.
     */
    public function unpinComment(Comment $comment): void
    {
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => null]);

        $this->dispatch('$refresh');
    }

    /**
     * Toggle subscription to comment notifications for this commentable.
     */
    public function toggleSubscription(): void
    {
        if (! Auth::check()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        if ($this->isSubscribed) {
            $this->commentable->unsubscribeUser($user);
            $this->isSubscribed = false;
        } else {
            $this->commentable->subscribeUser($user);
            $this->isSubscribed = true;
        }
    }

    /**
     * Toggle a reply form for a comment.
     */
    public function toggleReplyForm(int $commentId): void
    {
        $this->toggleForm('reply', $commentId);
    }

    /**
     * Toggle an edit form for a comment.
     */
    public function toggleEditForm(Comment $comment): void
    {
        $this->toggleForm('edit', $comment->id, $comment->body);
    }

    /**
     * Toggle descendants visibility for a comment.
     */
    public function toggleDescendants(int $commentId): void
    {
        $this->showDescendants[$commentId] = ! ($this->showDescendants[$commentId] ?? false);

        // Load descendants if showing and not already loaded
        if ($this->showDescendants[$commentId] && ! isset($this->loadedDescendants[$commentId])) {
            $this->loadDescendants($commentId);
        }
    }

    /**
     * Load descendants for a specific root comment.
     */
    public function loadDescendants(int $commentId): void
    {
        $comment = Comment::query()->find($commentId);
        if (
            ! $comment ||
            $comment->commentable_id !== $this->getCommentableId() ||
            $comment->commentable_type !== $this->commentable::class
        ) {
            return;
        }

        $descendants = $this->commentable->loadDescendants($comment);

        $this->loadedDescendants[$commentId] = $descendants;
    }

    /**
     * Check if a user has reacted to a comment.
     */
    public function hasReacted(int $commentId): bool
    {
        if (! Auth::check()) {
            return false;
        }

        // For performance, check the eager-loaded reactions.
        return in_array($commentId, $this->userReactionIds());
    }

    /**
     * Get the hash ID for a comment without loading the commentable.
     */
    public function getCommentHashId(int $commentId): string
    {
        $tabHash = $this->commentable->getCommentTabHash();

        return $tabHash ? $tabHash.'-comment-'.$commentId : 'comment-'.$commentId;
    }

    /**
     * Check if a comment has visible descendants for the current user.
     */
    public function hasVisibleDescendants(Comment $comment): bool
    {
        // Use reply count from the cache first
        if (isset($this->descendantCounts[$comment->id])) {
            return $this->descendantCounts[$comment->id] > 0;
        }

        $query = $comment->descendants();
        $user = Auth::user();

        if ($user === null) {
            return $query->clean()->exists();
        }

        if ($user->isModOrAdmin()) {
            return $query->exists();
        }

        return $query->where(function ($q) use ($user): void {
            $q->where('spam_status', SpamStatus::CLEAN->value)
                ->orWhere('user_id', $user->id);
        })->exists();
    }

    /**
     * Get descendant count for a comment.
     */
    public function getDescendantCount(int $commentId): int
    {
        return $this->descendantCounts[$commentId] ?? 0;
    }

    /**
     * Check if a form is visible.
     */
    public function isFormVisible(string $type, int $commentId): bool
    {
        $key = $this->getFormKey($type, $commentId);

        return $this->formStates[$key]['visible'] ?? false;
    }

    /**
     * Get form body content.
     */
    public function getFormBody(string $type, int $commentId): string
    {
        $key = $this->getFormKey($type, $commentId);

        return $this->formStates[$key]['body'] ?? '';
    }

    /**
     * Initialize subscription status.
     */
    protected function initializeSubscriptionStatus(): void
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $this->isSubscribed = $this->commentable->isUserSubscribed($user);
        }
    }

    /**
     * Initialize descendant counts for root comments.
     */
    protected function initializeDescendantCounts(): void
    {
        $this->descendantCounts = $this->commentable->getDescendantCounts();
    }

    /**
     * Check the rate limit for comment creation.
     */
    protected function checkRateLimit(string $errorKey): bool
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isModOrAdmin()) {
            return true;
        }

        $key = 'comment-creation:'.Auth::id();
        $maxAttempts = config('comments.rate_limiting.max_attempts', 1);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError($errorKey, sprintf('Too many comment attempts. Please wait %d seconds before commenting again.', $seconds));

            return false;
        }

        return true;
    }

    /**
     * Apply the rate limit after successful comment creation.
     */
    protected function applyRateLimit(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isModOrAdmin()) {
            $key = 'comment-creation:'.Auth::id();
            $rateLimitDuration = config('comments.rate_limiting.duration_seconds', 30);
            RateLimiter::hit($key, $rateLimitDuration);
        }
    }

    /**
     * Validate comment input.
     */
    protected function validateComment(string $fieldKey, string $fieldName = 'comment'): void
    {
        $minLength = config('comments.validation.min_length', 3);
        $maxLength = config('comments.validation.max_length', 10000);

        $this->validate([
            $fieldKey => sprintf('required|string|min:%s|max:%s', $minLength, $maxLength),
        ], [], [
            $fieldKey => $fieldName,
        ]);
    }

    /**
     * Validate parent comment exists and belongs to this commentable.
     */
    protected function validateParentComment(int $parentId): Comment
    {
        $parentComment = Comment::query()
            ->where('id', $parentId)
            ->where('commentable_id', $this->getCommentableId())
            ->where('commentable_type', $this->commentable::class)
            ->first();

        abort_unless($parentComment !== null, 404, 'Parent comment not found');

        return $parentComment;
    }

    /**
     * Validate comment belongs to this commentable.
     */
    protected function validateCommentBelongsToCommentable(Comment $comment): void
    {
        abort_if(
            $comment->commentable_id !== $this->getCommentableId() ||
            $comment->commentable_type !== $this->commentable::class,
            403,
            'Cannot perform action on comment from different page'
        );
    }

    /**
     * Store a new comment.
     */
    protected function storeComment(string $body, ?int $parentId = null): Comment
    {
        return $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'body' => $body,
            'parent_id' => $parentId,
            'user_ip' => request()->ip() ?? '',
            'user_agent' => request()->userAgent() ?? '',
            'referrer' => request()->header('referer') ?? '',
        ]);
    }

    /**
     * Get a form key for state management.
     */
    protected function getFormKey(string $type, int $commentId): string
    {
        return sprintf('%s-%d', $type, $commentId);
    }

    /**
     * Toggle form visibility.
     */
    protected function toggleForm(string $type, int $commentId, ?string $initialBody = null): void
    {
        $key = $this->getFormKey($type, $commentId);

        // Close another form type for the same comment
        $otherType = $type === 'reply' ? 'edit' : 'reply';
        $otherKey = $this->getFormKey($otherType, $commentId);
        unset($this->formStates[$otherKey]);

        if (! isset($this->formStates[$key]['visible'])) {
            $this->formStates[$key] = [
                'visible' => true,
                'body' => $initialBody ?? '',
            ];
        } else {
            $this->formStates[$key]['visible'] = ! $this->formStates[$key]['visible'];
            if ($this->formStates[$key]['visible'] && $initialBody !== null) {
                $this->formStates[$key]['body'] = $initialBody;
            }
        }
    }

    /**
     * Hide a form.
     */
    protected function hideForm(string $type, int $commentId): void
    {
        $key = $this->getFormKey($type, $commentId);
        unset($this->formStates[$key]);
    }

    /**
     * Get the commentable model's ID.
     */
    protected function getCommentableId(): int|string
    {
        /** @var Model&Commentable<Model> $model */
        $model = $this->commentable;

        return $model->getKey();
    }

    /**
     * Filter comments based on visibility permissions.
     *
     * @param  LengthAwarePaginator<int, Comment>  $comments
     * @return Collection<int, Comment>
     */
    protected function filterVisibleComments(LengthAwarePaginator $comments): Collection
    {
        $user = Auth::user();
        $collection = $comments->getCollection();

        if ($user === null) {
            return $collection->filter(fn ($comment): bool => $comment->isSpamClean());
        }

        if ($user->isModOrAdmin()) {
            return $collection;
        }

        // For regular users: clean comments + their own comments
        return $collection->filter(fn ($comment): bool => $comment->isSpamClean() || $comment->user_id === $user->id);
    }

    /**
     * Handle comment moderation updates.
     */
    #[On('comment-moderation-refresh')]
    public function refreshComments(): void
    {
        // Clear cached computed properties
        unset($this->commentCount);

        // Force a component refresh
        $this->dispatch('$refresh');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $user = Auth::user();

        // Select the root comments based on the user.
        $rootCommentsQuery = $this->commentable->rootComments();
        if ($user === null) {
            $rootCommentsQuery->clean();
        } elseif (! $user->isModOrAdmin()) {
            $rootCommentsQuery->where(function ($q) use ($user): void {
                $q->clean()->orWhere('user_id', $user->id);
            });
        }

        $rootComments = $rootCommentsQuery->paginate(perPage: 10, pageName: 'commentPage');
        $visibleRootComments = $rootComments->getCollection();

        return view('livewire.comment-component', [
            'rootComments' => $rootComments,
            'visibleRootComments' => $visibleRootComments,
        ]);
    }
}
