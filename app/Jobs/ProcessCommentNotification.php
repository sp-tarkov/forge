<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Notifications\NewCommentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class ProcessCommentNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comment $comment
    ) {
        // Delay job by 5 minutes to allow for comment deletion
        $this->delay(now()->addMinutes(5));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload the comment to check if it still exists and hasn't been soft-deleted
        $freshComment = Comment::query()
            ->whereNull('deleted_at')
            ->find($this->comment->id);

        if (! $freshComment) {
            return;
        }

        // Don't send notifications for spam comments
        if ($freshComment->isSpam()) {
            return;
        }

        // Get all subscribers for the commentable
        /** @var Commentable<Model> $commentable */
        $commentable = $freshComment->commentable;
        $subscribers = $commentable->getSubscribers();

        // Filter out the comment author (they shouldn't be notified of their own comment)
        $subscribers = $subscribers->filter(fn ($user): bool => $user->id !== $freshComment->user_id);

        if ($subscribers->isEmpty()) {
            return;
        }

        // Filter out users who have disabled email notifications
        $emailSubscribers = $subscribers->filter(fn ($user) => $user->email_notifications_enabled);

        // Send email notifications
        if ($emailSubscribers->isNotEmpty()) {
            Notification::send($emailSubscribers, new NewCommentNotification($freshComment));
        }

        // Send in-app notifications to all subscribers (regardless of email preference)
        foreach ($subscribers as $user) {
            $user->notify(new NewCommentNotification($freshComment));
        }
    }
}
