<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Jobs\CheckCommentForSpam;
use App\Models\Comment;
use App\Services\CommentSpamChecker;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Action extends Component
{
    /**
     * The comment instance.
     */
    #[Locked]
    public Comment $comment;

    /**
     * The state of the confirmation dialog for soft deleting the comment.
     */
    public bool $confirmCommentSoftDelete = false;

    /**
     * The state of the confirmation dialog for hard deleting the comment thread.
     */
    public bool $confirmCommentHardDelete = false;

    /**
     * The state of the confirmation dialog for marking the comment as spam.
     */
    public bool $confirmCommentMarkSpam = false;

    /**
     * The state of the confirmation dialog for marking the comment as ham.
     */
    public bool $confirmCommentMarkHam = false;

    /**
     * The state of the confirmation dialog for restoring the comment.
     */
    public bool $confirmCommentRestore = false;

    /**
     * The state of the confirmation dialog for checking the comment for spam using Akismet.
     */
    public bool $confirmCommentCheckSpam = false;

    /**
     * Whether a spam check is currently in progress.
     */
    public bool $spamCheckInProgress = false;

    /**
     * The initial spam check timestamp for comparison.
     */
    public ?string $spamCheckStartedAt = null;

    /**
     * Soft delete the comment by setting the deleted_at timestamp.
     */
    public function softDelete(): void
    {
        $this->confirmCommentSoftDelete = false;

        $this->authorize('softDelete', $this->comment);

        $this->comment->deleted_at = now();
        $this->comment->save();

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated.'.$this->comment->id, deleted: true);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully deleted!');
    }

    /**
     * Hard delete the comment thread (comment and all descendants if root comment).
     */
    public function hardDeleteThread(): void
    {
        $this->confirmCommentHardDelete = false;

        $this->authorize('hardDelete', $this->comment);

        // If this is a root comment, delete all descendants
        if ($this->comment->isRoot()) {
            $this->comment->descendants()->forceDelete();
        }

        // Force delete the comment itself
        $this->comment->forceDelete();

        // Dispatch events to refresh related components
        $this->dispatch('comment-deleted.'.$this->comment->id);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment thread successfully deleted!');
    }

    /**
     * Mark the comment as spam manually (without API check).
     */
    public function markAsSpam(): void
    {
        $this->confirmCommentMarkSpam = false;

        $this->authorize('markAsSpam', $this->comment);

        // Immediately mark as spam for UI updates
        $this->comment->markAsSpamByModerator(auth()->id());

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated.'.$this->comment->id, spam: true);
        $this->dispatch('comment-moderation-refresh');
        $this->dispatch('$refresh');

        // Defer the service call to Akismet
        defer(fn () => app(CommentSpamChecker::class)->markAsSpam($this->comment));

        flash()->success('Comment successfully marked as spam!');
    }

    /**
     * Mark the comment as clean (not spam).
     */
    public function markAsHam(): void
    {
        $this->confirmCommentMarkHam = false;

        $this->authorize('markAsHam', $this->comment);

        // Immediately mark as clean for UI updates
        $this->comment->markAsHam();

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated.'.$this->comment->id, spam: false);
        $this->dispatch('comment-moderation-refresh');
        $this->dispatch('$refresh');

        // Defer the service call to Akismet
        defer(fn () => app(CommentSpamChecker::class)->markAsHam($this->comment));

        flash()->success('Comment successfully marked as clean!');
    }

    /**
     * Restore a soft deleted comment.
     */
    public function restore(): void
    {
        $this->confirmCommentRestore = false;

        $this->authorize('restore', $this->comment);

        $this->comment->deleted_at = null;
        $this->comment->save();

        // Dispatch events to refresh related components
        $this->dispatch('comment-updated.'.$this->comment->id, deleted: false);
        $this->dispatch('comment-moderation-refresh');

        flash()->success('Comment successfully restored!');
    }

    /**
     * Check the comment for spam using Akismet API.
     */
    public function checkForSpam(): void
    {
        $this->confirmCommentCheckSpam = false;

        $this->authorize('checkForSpam', $this->comment);

        // Store the current spam check timestamp
        $this->spamCheckStartedAt = $this->comment->spam_checked_at?->toISOString();
        $this->spamCheckInProgress = true;

        // Dispatch the spam check job
        CheckCommentForSpam::dispatch($this->comment, isRecheck: true);

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

        // Refresh the comment model
        $this->comment->refresh();

        // Check if spam check has completed (timestamp changed or recheck count increased)
        $timestampChanged = $this->comment->spam_checked_at?->toISOString() !== $this->spamCheckStartedAt;

        if ($timestampChanged) {
            $this->spamCheckInProgress = false;

            // Dispatch events to refresh the UI
            $this->dispatch('comment-updated.'.$this->comment->id, spam: $this->comment->isSpam());
            $this->dispatch('comment-moderation-refresh');
            $this->dispatch('$refresh');

            // Show result message based on spam status and metadata
            if ($this->comment->isSpam()) {
                flash()->warning('Comment has been identified as spam by Akismet.');
            } elseif ($this->comment->spam_metadata && isset($this->comment->spam_metadata['error'])) {
                // Check for API errors in metadata
                flash()->error('Spam check failed: '.($this->comment->spam_metadata['error_message'] ?? 'API error'));
            } else {
                flash()->success('Comment has been verified as clean by Akismet.');
            }

            // Stop polling
            $this->dispatch('stop-spam-check-polling');
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
