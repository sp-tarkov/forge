<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Commentable;
use App\Facades\CachedGate;
use App\Jobs\CheckCommentForSpam;
use App\Jobs\ProcessCommentNotification;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;

class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $comment->updateRootId();

        // Subscribe the commenter to future comments.
        /** @var Commentable<Model>|null $commentable */
        $commentable = $comment->commentable;

        if ($commentable !== null) {
            $commentable->subscribeUser($comment->user);
        }

        // Dispatch the spam check job.
        dispatch(new CheckCommentForSpam($comment));

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
