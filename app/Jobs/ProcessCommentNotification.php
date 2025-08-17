<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Commentable;
use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

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
     *
     * @throws Throwable
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
        $subscribers = $subscribers->filter(fn (User $user): bool => $user->id !== $freshComment->user_id);

        if ($subscribers->isEmpty()) {
            return;
        }

        // Filter out users who have already been notified for this comment
        $subscribersToNotify = $subscribers->reject(fn (User $user): bool => NotificationLog::hasBeenSent(
            $freshComment,
            $user->id,
            NewCommentNotification::class
        ));

        if ($subscribersToNotify->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($subscribersToNotify, $freshComment): void {
            // Send notifications to subscribers who haven't been notified yet
            Notification::send($subscribersToNotify, new NewCommentNotification($freshComment));

            // Record that notifications have been sent
            foreach ($subscribersToNotify as $user) {
                $notificationType = $user->email_notifications_enabled
                    ? NotificationType::ALL
                    : NotificationType::DATABASE;

                NotificationLog::recordSent(
                    $freshComment,
                    $user->id,
                    NewCommentNotification::class,
                    $notificationType
                );
            }
        });
    }
}
