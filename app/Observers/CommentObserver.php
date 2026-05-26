<?php

declare(strict_types=1);

namespace App\Observers;

use App\Facades\CachedGate;
use App\Jobs\CheckCommentForSpam;
use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;

final class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $comment->updateRootId();

        // Only run the Akismet spam check when the integration is enabled. With Akismet off, mark the comment clean
        // inline so it never flickers through the PENDING ribbon state. Guard the inline mark on PENDING so an
        // explicitly-seeded status (used in tests and admin tooling) is not clobbered.
        if (config()->boolean('akismet.enabled', false)) {
            dispatch(new CheckCommentForSpam($comment));
        } elseif ($comment->isPendingSpamCheck()) {
            $comment->markAsClean(metadata: ['reason' => 'akismet_disabled'], quiet: true);
        }

        // Dispatch the comment notification job.
        dispatch(new ProcessCommentNotification($comment));
    }

    /**
     * Handle the Comment "updated" event.
     */
    public function updated(Comment $comment): void
    {
        if ($comment->wasChanged('parent_id')) {
            $comment->updateRootId();
            $comment->updateChildRootIds();
        }

        // Clear cached gate permissions for this comment when it's updated
        CachedGate::clearForModel($comment);
    }

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void
    {
        // Clear cached gate permissions for this comment when it's deleted
        CachedGate::clearForModel($comment);
    }

    /**
     * Handle the Comment "restored" event.
     */
    public function restored(Comment $comment): void
    {
        // Clear cached gate permissions for this comment when it's restored
        CachedGate::clearForModel($comment);
    }
}
