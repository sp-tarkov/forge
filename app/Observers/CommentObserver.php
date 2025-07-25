<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Commentable;
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

        // Ensure default subscriptions exist for the commentable.
        /** @var Commentable<Model> $commentable */
        $commentable = $comment->commentable;
        $commentable->ensureDefaultSubscriptions();

        // Dispatch the spam check job.
        CheckCommentForSpam::dispatch($comment);

        // Dispatch the comment notification job.
        ProcessCommentNotification::dispatch($comment);
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
    }
}
