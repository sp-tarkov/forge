<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Comment;

class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $comment->updateRootId();
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
