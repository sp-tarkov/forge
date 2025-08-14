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
use Illuminate\Support\Facades\Gate;
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
     * Show replies flags indexed by comment ID.
     *
     * @var array<int, bool>
     */
    public array $showReplies = [];

    /**
     * Loaded replies indexed by comment ID.
     *
     * @var array<int, Collection<int, Comment>>
     */
    public array $loadedReplies = [];

    /**
     * Reply counts indexed by comment ID.
     *
     * @var array<int, int>
     */
    public array $replyCounts = [];

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
        $this->initializeReplyCounts();
    }

    /**
     * Get the total comment count.
     */
    #[Computed(persist: true)]
    public function commentCount(): int
    {
        return $this->commentable->comments()
            ->get()
            ->filter(fn ($comment) => Gate::forUser(Auth::user())->allows('view', $comment))
            ->count();
    }

    /**
     * Get user reactions for visible comments.
     *
     * @return array<int>
     */
    #[Computed(persist: true)]
    public function userReactionIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();

        $viewableCommentIds = $this->commentable->comments()
            ->get()
            ->filter(fn ($comment) => Gate::forUser($user)->allows('view', $comment))
            ->pluck('id')
            ->toArray();

        return $user->commentReactions()
            ->whereIn('comment_id', $viewableCommentIds)
            ->pluck('comment_id')
            ->toArray();
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
        unset($this->userReactionIds);

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

        // Update reply counts and clear loaded replies for the parent
        $parentComment = Comment::query()->find($parentId);
        if ($parentComment && $parentComment->isRoot()) {
            $rootId = $parentComment->id;
        } else {
            $rootId = $parentComment->root_id;
        }

        if ($rootId) {
            $this->replyCounts[$rootId] = ($this->replyCounts[$rootId] ?? 0) + 1;
            // Clear loaded replies to force reload when toggled
            unset($this->loadedReplies[$rootId]);
            // Automatically show replies after creating a new reply
            $this->showReplies[$rootId] = true;
            $this->loadReplies($rootId);
        }

        // Clear cached computed properties.
        unset($this->commentCount);
        unset($this->userReactionIds);

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
        $fieldKey = sprintf('formStates.%s.body', $formKey);
        $body = $this->formStates[$formKey]['body'] ?? '';

        $this->validateComment($fieldKey, 'comment');

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
        $hasNoChildren = $comment->replies()->count() === 0 && $comment->descendants()->count() === 0;

        if ($isWithinTimeLimit && $hasNoChildren) {
            // Update reply counts if this is a reply being permanently deleted
            if (! $comment->isRoot()) {
                $rootId = $comment->root_id;
                if ($rootId && isset($this->replyCounts[$rootId])) {
                    $this->replyCounts[$rootId] = max(0, $this->replyCounts[$rootId] - 1);
                    // Clear loaded replies to force reload
                    unset($this->loadedReplies[$rootId]);
                }
            }

            $comment->delete();
        } else {
            $comment->update(['deleted_at' => now()]);
        }

        // Clear cached computed properties.
        unset($this->commentCount);
        unset($this->userReactionIds);

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

        unset($this->userReactionIds);
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
     * Toggle a reply's visibility for a comment.
     */
    public function toggleReplies(int $commentId): void
    {
        $this->showReplies[$commentId] = ! ($this->showReplies[$commentId] ?? false);

        // Load replies if showing and not already loaded
        if ($this->showReplies[$commentId] && ! isset($this->loadedReplies[$commentId])) {
            $this->loadReplies($commentId);
        }
    }

    /**
     * Load replies for a specific comment.
     */
    public function loadReplies(int $commentId): void
    {
        $comment = Comment::query()->find($commentId);

        if (! $comment || $comment->commentable_id !== $this->getCommentableId() || $comment->commentable_type !== $this->commentable::class) {
            return;
        }

        $replies = $this->commentable->loadRepliesForComment($comment);

        // Filter visible replies based on permissions
        $visibleReplies = $replies->filter(
            fn ($reply) => Gate::forUser(Auth::user())->allows('view', $reply)
        );

        $this->loadedReplies[$commentId] = $visibleReplies;
    }

    /**
     * Check if a user has reacted to a comment.
     */
    public function hasReacted(int $commentId): bool
    {
        return in_array($commentId, $this->userReactionIds());
    }

    /**
     * Check if a comment can still be edited.
     */
    public function canEditComment(Comment $comment): bool
    {
        if (! Auth::check() || $comment->user_id !== Auth::id()) {
            return false;
        }

        $editTimeLimit = config('comments.editing.edit_time_limit_minutes', 5);

        return $comment->created_at->diffInMinutes(now()) <= $editTimeLimit;
    }

    /**
     * Check if a comment has visible descendants for the current user.
     */
    public function hasVisibleDescendants(Comment $comment): bool
    {
        // Use reply count from cache first
        if (isset($this->replyCounts[$comment->id])) {
            return $this->replyCounts[$comment->id] > 0;
        }

        $query = $comment->descendants();

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if (! $user->isModOrAdmin()) {
                $query->where(function ($q) use ($user): void {
                    $q->where('spam_status', SpamStatus::CLEAN->value)
                        ->orWhere('user_id', $user->id);
                });
            }
        } else {
            $query->clean();
        }

        return $query->exists();
    }

    /**
     * Get reply count for a comment.
     */
    public function getReplyCount(int $commentId): int
    {
        return $this->replyCounts[$commentId] ?? 0;
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
     * Initialize reply counts for root comments.
     */
    protected function initializeReplyCounts(): void
    {
        $this->replyCounts = $this->commentable->getReplyCounts();
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
        $visibleComments = $comments->getCollection()->filter(
            fn ($comment) => Gate::forUser(Auth::user())->allows('view', $comment)
        );

        return $visibleComments->map(function ($comment) {
            $visibleDescendants = $comment->descendants->filter(
                fn ($descendant) => Gate::forUser(Auth::user())->allows('view', $descendant)
            );

            $comment->setRelation('descendants', $visibleDescendants);

            return $comment;
        });
    }

    /**
     * Handle comment moderation updates.
     */
    #[On('comment-moderation-refresh')]
    public function refreshComments(): void
    {
        // Clear cached computed properties
        unset($this->commentCount);
        unset($this->userReactionIds);

        // Force a component refresh
        $this->dispatch('$refresh');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $rootComments = $this->commentable->rootComments()
            ->paginate(perPage: 10, pageName: 'commentPage');

        $visibleRootComments = $this->filterVisibleComments($rootComments);

        return view('livewire.comment-component', [
            'rootComments' => $rootComments,
            'visibleRootComments' => $visibleRootComments,
        ]);
    }
}
