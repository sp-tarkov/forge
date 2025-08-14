<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Jobs\CheckCommentForSpam;
use App\Models\Comment;
use App\Services\CommentSpamChecker;
use App\Traits\Livewire\ModerationActionMenu;
use Error;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Action extends Component
{
    use ModerationActionMenu;

    /**
     * The comment ID.
     */
    #[Locked]
    public int $commentId;

    /**
     * The comment updated timestamp for wire:key.
     */
    #[Locked]
    public int $updatedAtTimestamp;

    /**
     * Whether the comment is pinned.
     */
    #[Locked]
    public bool $isPinned;

    /**
     * Whether the comment is deleted.
     */
    #[Locked]
    public bool $isDeleted;

    /**
     * Whether the comment is spam.
     */
    #[Locked]
    public bool $isSpam;

    /**
     * Whether the comment is a root comment.
     */
    #[Locked]
    public bool $isRoot;

    /**
     * Whether the comment can be rechecked for spam.
     */
    #[Locked]
    public bool $canBeRechecked;

    /**
     * Number of descendants (only for root comments).
     */
    #[Locked]
    public ?int $descendantsCount = null;

    /**
     * The spam check timestamp for comparison.
     */
    #[Locked]
    public ?string $spamCheckedAt = null;

    /**
     * Whether a spam check is currently in progress.
     */
    public bool $spamCheckInProgress = false;

    /**
     * The initial spam check timestamp for comparison.
     */
    public ?string $spamCheckStartedAt = null;

    /**
     * Get cached permissions for the current user.
     *
     * @return array<string, bool>
     */
    #[Computed(persist: true)]
    public function permissions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        // Skip permission cache if commentId is not yet initialized
        try {
            $commentId = $this->commentId;
        } catch (Error) {
            return [];
        }

        return Cache::remember(
            sprintf('comment.%d.permissions.%s', $this->commentId, $user->id),
            60, // Seconds
            fn (): array => [
                'viewActions' => Gate::allows('viewActions', $this->getComment()),
                'pin' => Gate::allows('pin', $this->getComment()),
                'softDelete' => Gate::allows('softDelete', $this->getComment()),
                'hardDelete' => Gate::allows('hardDelete', $this->getComment()),
                'restore' => Gate::allows('restore', $this->getComment()),
                'markAsSpam' => Gate::allows('markAsSpam', $this->getComment()),
                'markAsHam' => Gate::allows('markAsHam', $this->getComment()),
                'checkForSpam' => Gate::allows('checkForSpam', $this->getComment()),
            ]
        );
    }

    /**
     * Get the comment instance when needed for actions.
     */
    private function getComment(): Comment
    {
        return Comment::query()->findOrFail($this->commentId);
    }

    /**
     * Soft delete the comment by setting the deleted_at timestamp.
     */
    public function softDelete(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('softDelete', $comment);

        $comment->deleted_at = now();
        $comment->save();

        // Update local state
        $this->isDeleted = true;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated', $this->commentId, deleted: true);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully deleted!');
    }

    /**
     * Hard delete the comment thread (comment and all descendants if root comment).
     */
    public function hardDeleteThread(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('hardDelete', $comment);

        // If this is a root comment, delete all descendants
        if ($comment->isRoot()) {
            $comment->descendants()->forceDelete();
        }

        // Force delete the comment itself
        $comment->forceDelete();

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-deleted.'.$this->commentId);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment thread successfully deleted!');
    }

    /**
     * Mark the comment as spam manually (without API check).
     */
    public function markAsSpam(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('markAsSpam', $comment);

        // Immediately mark as spam for UI updates
        $comment->markAsSpamByModerator(auth()->id());

        // Update local state
        $this->isSpam = true;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated', $this->commentId, spam: true);
        $this->dispatch('comment-moderation-refresh');
        $this->dispatch('$refresh');

        // Defer the service call to Akismet
        defer(fn () => app(CommentSpamChecker::class)->markAsSpam($comment));

        flash()->success('Comment successfully marked as spam!');
    }

    /**
     * Mark the comment as clean (not spam).
     */
    public function markAsHam(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('markAsHam', $comment);

        // Immediately mark as clean for UI updates
        $comment->markAsHam();

        // Update local state
        $this->isSpam = false;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated', $this->commentId, spam: false);
        $this->dispatch('comment-moderation-refresh');
        $this->dispatch('$refresh');

        // Defer the service call to Akismet
        defer(fn () => app(CommentSpamChecker::class)->markAsHam($comment));

        flash()->success('Comment successfully marked as clean!');
    }

    /**
     * Restore a soft deleted comment.
     */
    public function restore(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('restore', $comment);

        $comment->deleted_at = null;
        $comment->save();

        // Update local state
        $this->isDeleted = false;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated', $this->commentId, deleted: false);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully restored!');
    }

    /**
     * Check the comment for spam using Akismet API.
     */
    public function checkForSpam(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('checkForSpam', $comment);

        // Store the current spam check timestamp
        $this->spamCheckStartedAt = $this->spamCheckedAt;
        $this->spamCheckInProgress = true;

        // Dispatch the spam check job
        CheckCommentForSpam::dispatch($comment, isRecheck: true);

        // Start polling for results
        $this->dispatch('start-spam-check-polling');

        flash()->info('Checking comment for spam... Please wait.');
    }

    /**
     * Poll for spam check completion.
     */
    public function pollSpamCheckStatus(): void
    {
        if (! $this->spamCheckInProgress) {
            return;
        }

        // Get fresh comment data
        $comment = $this->getComment();

        // Check if spam check has completed (timestamp changed)
        $newSpamCheckedAt = $comment->spam_checked_at?->toISOString();
        $timestampChanged = $newSpamCheckedAt !== $this->spamCheckStartedAt;

        if ($timestampChanged) {
            $this->spamCheckInProgress = false;

            // Update local state
            $this->isSpam = $comment->isSpam();
            $this->canBeRechecked = $comment->canBeRechecked();
            $this->spamCheckedAt = $newSpamCheckedAt;

            // Dispatch events to refresh the UI
            $this->dispatch('comment-updated', $this->commentId, spam: $this->isSpam);
            $this->dispatch('comment-moderation-refresh');
            $this->dispatch('$refresh');

            // Show result message based on spam status and metadata
            if ($comment->isSpam()) {
                flash()->warning('Comment has been identified as spam by Akismet.');
            } elseif ($comment->spam_metadata && isset($comment->spam_metadata['error'])) {
                // Check for API errors in metadata
                flash()->error('Spam check failed: '.($comment->spam_metadata['error_message'] ?? 'API error'));
            } else {
                flash()->success('Comment has been verified as clean by Akismet.');
            }

            // Stop polling
            $this->dispatch('stop-spam-check-polling');
        }
    }

    /**
     * Pin the comment.
     */
    public function pinComment(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => now()]);

        // Update local state
        $this->isPinned = true;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Send events to refresh related components.
        $this->dispatch('comment-updated', $this->commentId, pinned: true);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully pinned!');
    }

    /**
     * Unpin the comment.
     */
    public function unpinComment(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $comment = $this->getComment();
        $this->authorize('pin', $comment);

        $comment->update(['pinned_at' => null]);

        // Update local state
        $this->isPinned = false;

        $this->clearPermissionCache(sprintf('comment.%d.permissions.', $this->commentId).auth()->id());

        // Send events to refresh related components.
        $this->dispatch('comment-updated', $this->commentId, pinned: false);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully unpinned!');
    }

    /**
     * Handle comment updates from other components.
     */
    #[On('comment-updated')]
    public function handleCommentUpdate(int $commentId): void
    {
        // Only update if this is for our comment
        if ($commentId !== $this->commentId) {
            return;
        }

        $comment = $this->getComment();

        // Update local state with fresh data
        $this->isPinned = $comment->isPinned();
        $this->isDeleted = $comment->isDeleted();
        $this->isSpam = $comment->isSpam();
        $this->canBeRechecked = $comment->canBeRechecked();
        $this->spamCheckedAt = $comment->spam_checked_at?->toISOString();
        if ($this->isRoot) {
            // Note: We can't easily access the manager here, so we'll do a direct count query
            // This is only called when refreshing, not on initial load
            $this->descendantsCount = $comment->descendants()->count();
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.comment.action');
    }
}
