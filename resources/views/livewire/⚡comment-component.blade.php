<?php

declare(strict_types=1);

use App\Contracts\Commentable;
use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use App\Facades\CachedGate;
use App\Facades\Track;
use App\Jobs\CheckCommentForSpam;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\CommentVersion;
use App\Models\Mod;
use App\Models\User;
use App\Rules\DoesNotContainLogFile;
use App\Support\BatchPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * Single component for managing all comment functionality.
 */
new class extends Component
{
    use RendersMarkdownPreview;
    use UsesSpamProtection;
    use WithPagination;

    /**
     * List of abilities to batch-check for each comment.
     *
     * @var array<int, string>
     */
    protected const array COMMENT_ABILITIES = [
        'seeRibbon',
        'update',
        'delete',
        'viewActions',
        'modOwnerSoftDelete',
        'modOwnerRestore',
        'pin',
        'softDelete',
        'hardDelete',
        'restore',
        'markAsSpam',
        'markAsHam',
        'checkForSpam',
        'showOwnerPinAction',
        'viewVersionHistory',
    ];

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
     * Delete modal state properties.
     */
    public bool $showDeleteModal = false;

    public ?int $deletingCommentId = null;

    public string $deleteModalTitle = '';

    public string $deleteModalMessage = '';

    public string $deleteModalAction = '';

    /**
     * Soft delete modal state properties.
     */
    public bool $showSoftDeleteModal = false;

    public ?int $softDeletingCommentId = null;

    /**
     * Mod owner soft delete modal state properties.
     */
    public bool $showModOwnerSoftDeleteModal = false;

    public ?int $modOwnerSoftDeletingCommentId = null;

    /**
     * Mod owner restore modal state properties.
     */
    public bool $showModOwnerRestoreModal = false;

    public ?int $modOwnerRestoringCommentId = null;

    /**
     * Hard delete modal state properties.
     */
    public bool $showHardDeleteModal = false;

    public ?int $hardDeletingCommentId = null;

    public int $hardDeleteDescendantCount = 0;

    // Pin/Unpin modal properties
    public bool $showPinModal = false;

    public bool $showUnpinModal = false;

    public ?int $pinningCommentId = null;

    // Spam action modal properties
    public bool $showMarkAsSpamModal = false;

    public bool $showMarkAsCleanModal = false;

    public bool $showCheckForSpamModal = false;

    public ?int $spamActionCommentId = null;

    // Restore modal properties
    public bool $showRestoreModal = false;

    public ?int $restoringCommentId = null;

    /**
     * Reason/note for moderation actions.
     */
    public string $moderationReason = '';

    /**
     * Spam check tracking state.
     *
     * @var array<int, array{inProgress: bool, startedAt: string|null}>
     */
    public array $spamCheckStates = [];

    // Version history modal properties
    public bool $showVersionModal = false;

    public ?int $viewingVersionId = null;

    public ?int $viewingVersionCommentId = null;

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

        return $this->commentable->comments()
            ->visibleToUser($user)
            ->count();
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
            $reactionQuery->where(function (Builder $q) use ($user): void {
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
        $comment = $this->storeComment($this->newCommentBody);

        Track::event(TrackingEventType::COMMENT_CREATE, $comment);

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
        $comment = $this->storeComment($body, $parentId);

        Track::event(TrackingEventType::COMMENT_CREATE, $comment);

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
    public function updateComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('update', $comment);
        $this->protectAgainstSpam();

        $formKey = $this->getFormKey('edit', $comment->id);
        $fieldKey = 'formStates.'.$formKey.'.body';
        $body = $this->formStates[$formKey]['body'] ?? '';

        $this->validateComment($fieldKey);

        // Create new version with updated content
        $nextVersionNumber = ($comment->versions()->max('version_number') ?? 0) + 1;
        $comment->versions()->create([
            'body' => mb_trim($body),
            'version_number' => $nextVersionNumber,
            'created_at' => now(),
        ]);

        $comment->update([
            'edited_at' => now(),
        ]);

        // Reload the latestVersion relationship
        $comment->unsetRelation('latestVersion');
        $comment->load('latestVersion');

        Track::event(TrackingEventType::COMMENT_EDIT, $comment);

        $this->hideForm('edit', $comment->id);
    }

    /**
     * Show delete confirmation modal.
     */
    public function confirmDeleteComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('delete', $comment);

        $this->deletingCommentId = $commentId;
        $this->deleteModalTitle = 'Delete Comment';
        $this->deleteModalMessage = 'Are you sure you want to delete this comment?';
        $this->deleteModalAction = 'delete';
        $this->showDeleteModal = true;
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(Comment|int|null $commentId = null): void
    {
        // Support both direct calls and modal confirmations
        if ($commentId instanceof Comment) {
            $comment = $commentId;
        } else {
            $commentId ??= $this->deletingCommentId;
            if (! $commentId) {
                return;
            }

            $comment = Comment::query()->findOrFail($commentId);
        }

        $this->validateCommentBelongsToCommentable($comment);
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

        Track::event(TrackingEventType::COMMENT_SOFT_DELETE, $comment);

        // Clear cached computed properties.
        unset($this->commentCount);

        $this->showDeleteModal = false;
        $this->deletingCommentId = null;

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
            Track::event(TrackingEventType::COMMENT_UNLIKE, $comment);
        } else {
            $user->commentReactions()->create(['comment_id' => $comment->id]);
            Track::event(TrackingEventType::COMMENT_LIKE, $comment);
        }

        // Get the updated reactions_count.
        $comment->loadCount('reactions');

        // Update the cached descendant.
        $this->updateCachedDescendant($comment);

        // Clear computed property.
        unset($this->userReactionIds);
    }

    /**
     * Show pin confirmation modal.
     */
    public function confirmPinComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $this->pinningCommentId = $commentId;
        $this->showPinModal = true;
    }

    /**
     * Show unpin confirmation modal.
     */
    public function confirmUnpinComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $this->pinningCommentId = $commentId;
        $this->showUnpinModal = true;
    }

    /**
     * Pin a comment.
     */
    public function pinComment(?int $commentId = null): void
    {
        $commentId ??= $this->pinningCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => now()]);

        Track::eventSync(
            TrackingEventType::COMMENT_PIN,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        flash()->success('Comment successfully pinned!');
        $this->showPinModal = false;
        $this->pinningCommentId = null;
        $this->moderationReason = '';
    }

    /**
     * Unpin a comment.
     */
    public function unpinComment(?int $commentId = null): void
    {
        $commentId ??= $this->pinningCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => null]);

        Track::eventSync(
            TrackingEventType::COMMENT_UNPIN,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        flash()->success('Comment successfully unpinned!');
        $this->showUnpinModal = false;
        $this->pinningCommentId = null;
        $this->moderationReason = '';
    }

    /**
     * Show soft delete confirmation modal.
     */
    public function confirmSoftDeleteComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('softDelete', $comment);

        $this->softDeletingCommentId = $commentId;
        $this->showSoftDeleteModal = true;
    }

    /**
     * Soft delete a comment.
     */
    public function softDeleteComment(?int $commentId = null): void
    {
        $commentId ??= $this->softDeletingCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('softDelete', $comment);

        $comment->update(['deleted_at' => now()]);

        Track::eventSync(
            TrackingEventType::COMMENT_SOFT_DELETE,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, deleted: true);

        $this->showSoftDeleteModal = false;
        $this->softDeletingCommentId = null;
        $this->moderationReason = '';

        flash()->success('Comment successfully deleted!');
    }

    /**
     * Show mod owner soft delete confirmation modal.
     */
    public function confirmModOwnerSoftDeleteComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('modOwnerSoftDelete', $comment);

        $this->modOwnerSoftDeletingCommentId = $commentId;
        $this->showModOwnerSoftDeleteModal = true;
    }

    /**
     * Mod owner soft delete a comment.
     */
    public function modOwnerSoftDeleteComment(?int $commentId = null): void
    {
        $commentId ??= $this->modOwnerSoftDeletingCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('modOwnerSoftDelete', $comment);

        $comment->update(['deleted_at' => now()]);

        Track::event(TrackingEventType::COMMENT_SOFT_DELETE, $comment);

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, deleted: true);

        $this->showModOwnerSoftDeleteModal = false;
        $this->modOwnerSoftDeletingCommentId = null;

        flash()->success('Comment successfully deleted!');
    }

    /**
     * Show mod owner restore confirmation modal.
     */
    public function confirmModOwnerRestoreComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('modOwnerRestore', $comment);

        $this->modOwnerRestoringCommentId = $commentId;
        $this->showModOwnerRestoreModal = true;
    }

    /**
     * Mod owner restore a comment.
     */
    public function modOwnerRestoreComment(?int $commentId = null): void
    {
        $commentId ??= $this->modOwnerRestoringCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('modOwnerRestore', $comment);

        $comment->update(['deleted_at' => null]);

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, deleted: false);

        $this->showModOwnerRestoreModal = false;
        $this->modOwnerRestoringCommentId = null;

        flash()->success('Comment successfully restored!');
    }

    /**
     * Show restore confirmation modal.
     */
    public function confirmRestoreComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('restore', $comment);

        $this->restoringCommentId = $commentId;
        $this->showRestoreModal = true;
    }

    /**
     * Restore a deleted comment.
     */
    public function restoreComment(?int $commentId = null): void
    {
        $commentId ??= $this->restoringCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('restore', $comment);

        $comment->update(['deleted_at' => null]);

        Track::eventSync(
            TrackingEventType::COMMENT_RESTORE,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, deleted: false);

        $this->showRestoreModal = false;
        $this->restoringCommentId = null;
        $this->moderationReason = '';

        flash()->success('Comment successfully restored!');
    }

    /**
     * Show mark as spam confirmation modal.
     */
    public function confirmMarkAsSpam(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('markAsSpam', $comment);

        $this->spamActionCommentId = $commentId;
        $this->showMarkAsSpamModal = true;
    }

    /**
     * Show mark as clean confirmation modal.
     */
    public function confirmMarkAsClean(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('markAsHam', $comment);

        $this->spamActionCommentId = $commentId;
        $this->showMarkAsCleanModal = true;
    }

    /**
     * Mark a comment as spam.
     */
    public function markCommentAsSpam(?int $commentId = null): void
    {
        $commentId ??= $this->spamActionCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('markAsSpam', $comment);

        $comment->markAsSpamByModerator(auth()->id());

        Track::eventSync(
            TrackingEventType::COMMENT_MARK_SPAM,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, spam: true);

        flash()->success('Comment marked as spam!');
        $this->showMarkAsSpamModal = false;
        $this->spamActionCommentId = null;
        $this->moderationReason = '';
    }

    /**
     * Mark a comment as clean (not spam).
     */
    public function markCommentAsHam(?int $commentId = null): void
    {
        $commentId ??= $this->spamActionCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('markAsHam', $comment);

        $comment->markAsHam();

        Track::eventSync(
            TrackingEventType::COMMENT_MARK_CLEAN,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        $this->updateCachedDescendant($comment);

        // Dispatch event to update the ribbon component.
        $this->dispatch('comment-updated', $commentId, spam: false);

        flash()->success('Comment marked as clean!');
        $this->showMarkAsCleanModal = false;
        $this->spamActionCommentId = null;
        $this->moderationReason = '';
    }

    /**
     * Show check for spam confirmation modal.
     */
    public function confirmCheckForSpam(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('checkForSpam', $comment);

        $this->spamActionCommentId = $commentId;
        $this->showCheckForSpamModal = true;
    }

    /**
     * Check a comment for spam.
     */
    public function checkCommentForSpam(?int $commentId = null): void
    {
        $commentId ??= $this->spamActionCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('checkForSpam', $comment);

        // Store the current spam check timestamp for polling.
        $this->spamCheckStates[$commentId] = [
            'inProgress' => true,
            'startedAt' => $comment->spam_checked_at?->toISOString(),
        ];

        dispatch(new CheckCommentForSpam($comment, isRecheck: true));

        // Start polling for results
        $this->dispatch('start-spam-check-polling', $commentId);

        $this->showCheckForSpamModal = false;
        $this->spamActionCommentId = null;

        flash()->info('Checking comment for spam...');
    }

    /**
     * Poll for spam check completion.
     */
    public function pollSpamCheckStatus(int $commentId): void
    {
        if (! isset($this->spamCheckStates[$commentId]) || ! $this->spamCheckStates[$commentId]['inProgress']) {
            return;
        }

        $comment = Comment::query()->find($commentId);
        if (! $comment) {
            return;
        }

        // Check if the spam check has completed (timestamp changed)
        $newSpamCheckedAt = $comment->spam_checked_at?->toISOString();
        $startedAt = $this->spamCheckStates[$commentId]['startedAt'];
        $timestampChanged = $newSpamCheckedAt !== $startedAt;

        if ($timestampChanged) {
            $this->spamCheckStates[$commentId]['inProgress'] = false;

            // Dispatch events to refresh the ribbon.
            $this->dispatch('comment-updated', $commentId, spam: $comment->isSpam());

            // Stop polling.
            $this->dispatch('stop-spam-check-polling', $commentId);

            // Show the result message based on spam status and metadata.
            if ($comment->isSpam()) {
                flash()->warning('Comment has been identified as spam.');
            } elseif ($comment->spam_metadata && isset($comment->spam_metadata['error'])) {
                flash()->error('Spam check failed: '.($comment->spam_metadata['error_message'] ?? 'API error'));
            } else {
                flash()->success('Comment has been verified as clean.');
            }
        }
    }

    /**
     * Show hard-delete confirmation modal.
     */
    public function confirmHardDeleteComment(int $commentId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('hardDelete', $comment);

        $this->hardDeletingCommentId = $commentId;
        $this->hardDeleteDescendantCount = $comment->isRoot() ? $this->getDescendantCount($commentId) : 0;
        $this->showHardDeleteModal = true;
    }

    /**
     * Hard-delete a comment and its descendants.
     */
    public function hardDeleteComment(?int $commentId = null): void
    {
        $commentId ??= $this->hardDeletingCommentId;
        if (! $commentId) {
            return;
        }

        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('hardDelete', $comment);

        // If this is a root comment, delete all descendants.
        if ($comment->isRoot()) {
            $comment->descendants()->delete();
        }

        // Delete the comment itself.
        $comment->delete();

        Track::eventSync(
            TrackingEventType::COMMENT_HARD_DELETE,
            $comment,
            isModerationAction: true,
            reason: $this->moderationReason ?: null
        );

        $this->showHardDeleteModal = false;
        $this->hardDeletingCommentId = null;
        $this->hardDeleteDescendantCount = 0;
        $this->moderationReason = '';

        flash()->success('Comment thread permanently deleted!');
    }

    /**
     * Show version history modal for a specific version.
     */
    public function openVersionModal(int $commentId, int $versionId): void
    {
        $comment = Comment::query()->findOrFail($commentId);
        $this->validateCommentBelongsToCommentable($comment);
        $this->authorize('viewVersionHistory', $comment);

        $this->viewingVersionId = $versionId;
        $this->viewingVersionCommentId = $commentId;
        $this->showVersionModal = true;
    }

    /**
     * Get the version being viewed in the modal.
     */
    #[Computed]
    public function viewingVersion(): ?CommentVersion
    {
        if (! $this->viewingVersionId) {
            return null;
        }

        return CommentVersion::find($this->viewingVersionId);
    }

    /**
     * Get the comment for the version being viewed in the modal.
     */
    #[Computed]
    public function viewingVersionComment(): ?Comment
    {
        if (! $this->viewingVersionCommentId) {
            return null;
        }

        return Comment::with('user')->find($this->viewingVersionCommentId);
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

        // Load descendants if showing and not already loaded.
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
        // Use the reply count from the cache first.
        if (isset($this->descendantCounts[$comment->id])) {
            return $this->descendantCounts[$comment->id] > 0;
        }

        $user = Auth::user();

        return $comment->descendants()
            ->visibleToUser($user)
            ->exists();
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
     * Handle a deep link to a specific comment.
     */
    public function handleDeepLink(int $commentId): void
    {
        $comment = Comment::query()->find($commentId);
        if (
            ! $comment ||
            $comment->commentable_id !== $this->getCommentableId() ||
            $comment->commentable_type !== $this->commentable::class
        ) {
            return;
        }

        // If it's a descendant comment, we need to load its root's descendants.
        if ($comment->root_id) {
            $this->showDescendants[$comment->root_id] = true;
            $this->loadDescendants($comment->root_id);
        }

        // Dispatch event to scroll to the comment after render. This is caught with AlpineJS.
        $this->dispatch('scroll-to-comment', commentId: $commentId);
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = Auth::user();

        // Select the root comments based on the user using the visibility scope.
        $rootComments = $this->commentable->rootComments()
            ->visibleToUser($user)
            ->paginate(perPage: 10, pageName: 'commentPage');

        $visibleRootComments = $rootComments->getCollection();

        // Auto-expand and load descendants for root comments on the current page that have replies.
        // This ensures replies are shown by default, but only for the visible page (not all comments).
        foreach ($visibleRootComments as $rootComment) {
            $commentId = $rootComment->id;
            $hasDescendants = ($this->descendantCounts[$commentId] ?? 0) > 0;

            if ($hasDescendants && ! isset($this->loadedDescendants[$commentId])) {
                $this->showDescendants[$commentId] = true;
                $this->loadDescendants($commentId);
            }
        }

        // Batch compute permissions for all visible comments (root + descendants)
        $permissions = $this->computePermissionsForVisibleComments($visibleRootComments);

        return [
            'rootComments' => $rootComments,
            'visibleRootComments' => $visibleRootComments,
            'permissions' => $permissions,
            'showDescendants' => $this->showDescendants,
            'loadedDescendants' => $this->loadedDescendants,
        ];
    }

    /**
     * Compute permissions for all visible comments in batch.
     *
     * @param  Collection<int, Comment>  $rootComments
     */
    protected function computePermissionsForVisibleComments(Collection $rootComments): BatchPermissions
    {
        // Collect all comments: root + all loaded descendants
        $allComments = [];

        foreach ($rootComments as $rootComment) {
            $allComments[] = $rootComment;

            // Add loaded descendants if they exist
            $descendants = $this->loadedDescendants[$rootComment->id] ?? null;
            if ($descendants !== null) {
                foreach ($descendants as $descendant) {
                    $allComments[] = $descendant;
                }
            }
        }

        // Set the commentable relation on all comments to prevent N+1 in policies.
        // The policy accesses $comment->commentable which would trigger a query for each comment.
        // Since we already have the commentable model, we can set the relation directly.
        $this->setCommentableRelationOnComments($allComments);

        // Batch compute permissions using CachedGate
        $permissionsArray = CachedGate::batchCheckMultiple(self::COMMENT_ABILITIES, $allComments);

        return new BatchPermissions($permissionsArray);
    }

    /**
     * Set the commentable relation on all comments to prevent N+1 queries in policies.
     *
     * @param  array<Comment>  $comments
     */
    protected function setCommentableRelationOnComments(array $comments): void
    {
        $commentable = $this->commentable;

        // Eager-load additionalAuthors if this is a Mod to prevent N+1 in policy checks
        if ($commentable instanceof Mod && ! $commentable->relationLoaded('additionalAuthors')) {
            $commentable->load('additionalAuthors');
        }

        // Set the commentable relation on each comment
        foreach ($comments as $comment) {
            $comment->setRelation('commentable', $commentable);
        }
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

        // Get the raw value and trim it before validation
        $rawValue = data_get($this, $fieldKey, '');
        $trimmedValue = Str::of($rawValue)->trim()->value();
        data_set($this, $fieldKey, $trimmedValue);

        $this->validate([
            $fieldKey => [
                'required',
                'string',
                sprintf('min:%s', $minLength),
                sprintf('max:%s', $maxLength),
                new DoesNotContainLogFile,
            ],
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
        $comment = $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'parent_id' => $parentId,
            'user_ip' => request()->ip() ?? '',
            'user_agent' => request()->userAgent() ?? '',
            'referrer' => request()->header('referer') ?? '',
        ]);

        // Create initial version with the body content
        $comment->versions()->create([
            'body' => mb_trim($body),
            'version_number' => 1,
            'created_at' => now(),
        ]);

        // Load the version for immediate display
        $comment->load('latestVersion');

        // Load parent relationship for replies to ensure it's available when rendering
        if ($parentId) {
            $comment->load([
                'parent:id,user_id',
                'parent.user:id,name,user_role_id',
                'parent.user.role:id,name,color_class,icon',
            ]);
        }

        // User is automatically subscribed when they create a comment (via Observer).
        $user = Auth::user();
        if ($user) {
            $this->isSubscribed = true;
        }

        return $comment;
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
     * Update a descendant comment in the loaded descendants cache.
     */
    protected function updateCachedDescendant(Comment $comment): void
    {
        // Only process descendant comments.
        if ($comment->isRoot()) {
            return;
        }

        $rootId = $comment->root_id;
        if (! $rootId || ! isset($this->loadedDescendants[$rootId])) {
            return;
        }

        // Find and update the comment in the loaded descendants' collection.
        $this->loadedDescendants[$rootId] = $this->loadedDescendants[$rootId]->map(function (Comment $descendant) use ($comment): Comment {
            if ($descendant->id === $comment->id) {
                $freshComment = $comment->fresh();
                if ($freshComment) {
                    $freshComment->loadCount('reactions');
                    $freshComment->load('user');

                    return $freshComment;
                }
            }

            return $descendant;
        });
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
};
?>

<div
    x-data="{
        init() {
            // Check for deep linked comment on page load
            const hash = window.location.hash;
            if (!hash || !hash.includes('comment-')) {
                return;
            }
            const commentId = hash.match(/comment-(\d+)/)?.[1];
            if (commentId && $wire?.handleDeepLink) {
                $wire.handleDeepLink(parseInt(commentId, 10));
                console.log('init');
            }
        }
    }"
    @scroll-to-comment.window="
        const { commentId } = $event.detail;
        const elementId = `reply-container-${commentId}`;
        requestAnimationFrame(() => {
            const element = document.getElementById(elementId);
            if (!element) return;

            element.scrollIntoView({ behavior: 'smooth', block: 'start' });

            const highlightClasses = ['bg-yellow-100', 'dark:bg-sky-700', 'transition-colors', 'duration-1000'];
            element.classList.add(...highlightClasses);
            setTimeout(() => element.classList.remove(...highlightClasses), 2000);
        });
    "
>
    @if ($visibleRootComments->count() === 0)
        <x-comment.empty-state
            :is-guest="auth()->guest()"
            :commentable="$commentable"
        />
    @endif

    @auth
        @if (CachedGate::allows('create', [App\Models\Comment::class, $commentable]))
            <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">
                        {{ __('Discussion') }}
                        <span class="font-normal text-slate-400">{{ '(' . $this->commentCount . ')' ?? '' }}</span>
                    </h2>

                    <flux:button
                        wire:click="toggleSubscription"
                        data-test="subscription-toggle"
                        variant="{{ $isSubscribed ? 'primary' : 'outline' }}"
                        icon="{{ $isSubscribed ? 'bell' : 'bell-alert' }}"
                        size="sm"
                    >
                        {{ $isSubscribed ? __('Subscribed') : __('Subscribe') }}
                    </flux:button>
                </div>
                <div class="flex items-start">
                    <div class="mr-3">
                        <flux:avatar
                            src="{{ auth()->user()->profile_photo_url }}"
                            color="auto"
                            color:seed="{{ auth()->user()->id }}"
                            circle="circle"
                        />
                    </div>
                    <div class="flex-1">
                        <x-comment.form
                            form-key="newCommentBody"
                            data-test="new-comment-body"
                            submit-action="createComment"
                            submit-text="{{ __('Post Comment') }}"
                        />
                    </div>
                </div>
            </div>
        @else
            <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                    <flux:icon.chat-bubble-left-ellipsis class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    @if ($commentable->comments_disabled)
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Comments Disabled') }}
                        </h3>
                        <p>{{ __('Comments are disabled.') }}</p>
                    @elseif (!auth()->user()->hasVerifiedEmail())
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Email Verification Required') }}</h3>
                        <p class="mb-4">{{ __('Please verify your email address to participate in discussions.') }}</p>
                        <flux:button
                            href="{{ route('verification.notice') }}"
                            size="sm"
                        >{{ __('Verify Email') }}</flux:button>
                    @else
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Cannot Comment') }}
                        </h3>
                        <p>{{ __('You do not have permission to comment on this content.') }}</p>
                    @endif
                </div>
            </div>
        @endif
    @endauth

    @if ($rootComments->hasPages() && $visibleRootComments->count() > 0)
        <div class="mb-4">
            {{ $rootComments->onEachSide(1)->links(data: ['scrollTo' => '#comments']) }}
        </div>
    @endif

    @foreach ($visibleRootComments as $comment)
        <div
            wire:key="comment-container-{{ $comment->id }}"
            class="comment-container-{{ $comment->id }} relative mb-4 last:mb-0"
            x-data="{
                commentId: {{ $comment->id }},
                isExpanded: true,
                init() {
                    const storageKey = 'comment-expanded-' + this.commentId;
                    const saved = localStorage.getItem(storageKey);
                    if (saved !== null) {
                        this.isExpanded = saved === 'true';
                    }
                },
                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    const storageKey = 'comment-expanded-' + this.commentId;
                    localStorage.setItem(storageKey, this.isExpanded.toString());
                }
            }"
        >
            <livewire:ribbon.comment
                wire:key="ribbon-comment-{{ $comment->id }}"
                :comment-id="$comment->id"
                :spam-status="$comment->spam_status->value"
                :can-see-ribbon="$permissions->can($comment->id, 'seeRibbon')"
            />
            <div
                wire:key="comment-{{ $comment->id }}"
                class="p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none"
            >
                <x-comment.display
                    :comment="$comment"
                    :permissions="$permissions"
                    :manager="$this"
                    :commentable="$commentable"
                />

                <div
                    x-show="isExpanded"
                    x-collapse
                >
                    @if (($showDescendants[$comment->id] ?? false) && isset($loadedDescendants[$comment->id]))
                        <div class="mt-4 space-y-4">
                            @foreach ($loadedDescendants[$comment->id] as $reply)
                                <div
                                    wire:key="reply-container-{{ $reply->id }}"
                                    class="relative"
                                >
                                    <livewire:ribbon.comment
                                        wire:key="ribbon-reply-{{ $reply->id }}"
                                        :comment-id="$reply->id"
                                        :spam-status="$reply->spam_status->value"
                                        :can-see-ribbon="$permissions->can($reply->id, 'seeRibbon')"
                                    />
                                    <div
                                        id="reply-container-{{ $reply->id }}"
                                        wire:key="reply-{{ $reply->id }}"
                                        class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none"
                                    >
                                        <x-comment.display
                                            :comment="$reply"
                                            :permissions="$permissions"
                                            :manager="$this"
                                            :is-reply="true"
                                            :commentable="$commentable"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    @if ($rootComments->hasPages() && $visibleRootComments->count() > 0)
        <div class="mt-4">
            {{ $rootComments->onEachSide(1)->links(data: ['scrollTo' => '#comments']) }}
        </div>
    @endif

    {{-- Remove Comment Modal --}}
    @if ($showDeleteModal)
        <flux:modal
            wire:model.self="showDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="trash"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Remove Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('This action cannot be undone') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ $deleteModalMessage }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showDeleteModal = false"
                        data-test="cancel-delete-comment"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="deleteComment"
                        data-test="confirm-delete-comment"
                        variant="primary"
                        size="sm"
                        icon="trash"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        {{ __('Remove Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Hide Comment Modal --}}
    @if ($showSoftDeleteModal)
        <flux:modal
            wire:model.self="showSoftDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye-slash"
                            class="w-8 h-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Soft-delete Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Comment will be hidden but can be restored') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('This comment will be hidden from regular users but can be restored later by moderators.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showSoftDeleteModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="softDeleteComment"
                        variant="primary"
                        size="sm"
                        icon="eye-slash"
                        class="bg-amber-600 hover:bg-amber-700 text-white"
                    >
                        {{ __('Soft-delete Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Soft-delete Comment Modal --}}
    @if ($showModOwnerSoftDeleteModal)
        <flux:modal
            wire:model.self="showModOwnerSoftDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye-slash"
                            class="w-8 h-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Soft-delete Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Hide comment from public view') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to soft-delete this comment? The comment will be hidden from public view but can be restored by moderators.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showModOwnerSoftDeleteModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="modOwnerSoftDeleteComment"
                        variant="primary"
                        size="sm"
                        icon="eye-slash"
                        class="bg-amber-600 hover:bg-amber-700 text-white"
                    >
                        {{ __('Soft-delete Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Restore Comment Modal --}}
    @if ($showModOwnerRestoreModal)
        <flux:modal
            wire:model.self="showModOwnerRestoreModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-path"
                            class="w-8 h-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Restore Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Make comment visible again') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to restore this comment? The comment will become visible to all users again.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showModOwnerRestoreModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="modOwnerRestoreComment"
                        variant="primary"
                        size="sm"
                        icon="arrow-path"
                        class="bg-green-600 hover:bg-green-700 text-white"
                    >
                        {{ __('Restore Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Permanently Remove Comment Modal --}}
    @if ($showHardDeleteModal)
        <flux:modal
            wire:model.self="showHardDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="trash"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Permanently Remove Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('This action is irreversible') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        @if ($hardDeleteDescendantCount > 0)
                            {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                        @else
                            {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                        @endif
                    </flux:text>

                    @if ($hardDeleteDescendantCount > 0)
                        <div
                            class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <flux:icon
                                    name="exclamation-triangle"
                                    class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0"
                                />
                                <div>
                                    <flux:text class="text-amber-900 dark:text-amber-200 text-sm font-medium">
                                        {{ __('Attention!') }}
                                    </flux:text>
                                    <flux:text class="text-amber-800 dark:text-amber-300 text-sm mt-1">
                                        {{ __('This will also permanently delete :count replies in this comment thread. Consider using soft-delete instead if you want to preserve the replies.', ['count' => $hardDeleteDescendantCount]) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                        <flux:icon
                            name="shield-exclamation"
                            class="w-4 h-4 mr-2 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Permanent deletion') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            x-on:click="$wire.showHardDeleteModal = false; $wire.moderationReason = ''"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="hardDeleteComment"
                            variant="primary"
                            size="sm"
                            icon="trash"
                            class="bg-red-600 hover:bg-red-700 text-white"
                        >
                            {{ __('Remove Permanently') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Pin Comment Modal --}}
    @if ($showPinModal)
        <flux:modal
            wire:model.self="showPinModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="bookmark"
                            class="w-8 h-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Pin Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Highlight important comment') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to pin this comment? Pinned comments appear at the top of the comment section.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showPinModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="pinComment"
                        data-test="confirm-pin-comment"
                        variant="primary"
                        size="sm"
                        icon="bookmark"
                        class="bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        {{ __('Pin Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Unpin Comment Modal --}}
    @if ($showUnpinModal)
        <flux:modal
            wire:model.self="showUnpinModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="bookmark-slash"
                            class="w-8 h-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Unpin Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Remove from pinned section') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to unpin this comment? It will no longer appear at the top of the comment section.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showUnpinModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="unpinComment"
                        data-test="confirm-unpin-comment"
                        variant="primary"
                        size="sm"
                        icon="bookmark-slash"
                        class="bg-amber-600 hover:bg-amber-700 text-white"
                    >
                        {{ __('Unpin Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mark as Spam Modal --}}
    @if ($showMarkAsSpamModal)
        <flux:modal
            wire:model.self="showMarkAsSpamModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="shield-exclamation"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Mark as Spam') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Flag content as spam') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('This comment will be marked as spam and hidden from regular users.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showMarkAsSpamModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="markCommentAsSpam"
                        variant="primary"
                        size="sm"
                        icon="shield-exclamation"
                        class="bg-red-600 hover:bg-red-700 text-white"
                    >
                        {{ __('Mark as Spam') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mark as Clean Modal --}}
    @if ($showMarkAsCleanModal)
        <flux:modal
            wire:model.self="showMarkAsCleanModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="shield-check"
                            class="w-8 h-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Mark as Clean') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Confirm content is not spam') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to mark this comment as clean? This will remove any spam flags and make it visible to all users.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showMarkAsCleanModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="markCommentAsHam"
                        variant="primary"
                        size="sm"
                        icon="shield-check"
                        class="bg-green-600 hover:bg-green-700 text-white"
                    >
                        {{ __('Mark as Clean') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Check for Spam Modal --}}
    @if ($showCheckForSpamModal)
        <flux:modal
            wire:model.self="showCheckForSpamModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="magnifying-glass"
                            class="w-8 h-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Check for Spam') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Analyze comment for spam content') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('This will send the comment to the spam detection service for analysis. The system will automatically flag it if spam is detected.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showCheckForSpamModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="checkCommentForSpam"
                        variant="primary"
                        size="sm"
                        icon="magnifying-glass"
                        class="bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        {{ __('Check for Spam') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Restore Comment Modal --}}
    @if ($showRestoreModal)
        <flux:modal
            wire:model.self="showRestoreModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-path"
                            class="w-8 h-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Restore Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Make comment visible again') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to restore this comment? It will become visible to users again.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div
                    class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        x-on:click="$wire.showRestoreModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="restoreComment"
                        variant="primary"
                        size="sm"
                        icon="arrow-path"
                        class="bg-green-600 hover:bg-green-700 text-white"
                    >
                        {{ __('Restore Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Version History Modal --}}
    <flux:modal
        wire:model="showVersionModal"
        class="max-w-2xl"
    >
        @if ($this->viewingVersion && $this->viewingVersionComment)
            <div class="space-y-4">
                <flux:heading size="lg">
                    {{ __('Version :number', ['number' => $this->viewingVersion->version_number]) }}
                </flux:heading>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('By') }} {{ $this->viewingVersionComment->user->name }}
                    {{ __('on') }} {{ $this->viewingVersion->created_at->format('F j, Y \a\t g:i A') }}
                </div>

                <flux:separator />

                <div class="user-markdown text-gray-900 dark:text-slate-200">
                    {!! $this->viewingVersion->body_html !!}
                </div>
            </div>
        @endif
    </flux:modal>
</div>
