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
use Illuminate\Support\Facades\Log;
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
    ) {}

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
        /** @var Commentable<Model>|null $commentable */
        $commentable = $freshComment->commentable;

        if ($commentable === null) {
            return;
        }

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

        foreach ($subscribersToNotify as $user) {
            if (NotificationLog::hasBeenSent($freshComment, $user->id, NewCommentNotification::class)) {
                continue;
            }

            try {
                DB::transaction(function () use ($user, $freshComment): void {
                    $notificationType = $user->email_comment_notifications_enabled
                        ? NotificationType::ALL
                        : NotificationType::DATABASE;

                    // Record the notification log entry first
                    NotificationLog::recordSent(
                        $freshComment,
                        $user->id,
                        NewCommentNotification::class,
                        $notificationType
                    );

                    // Send the notification - if this fails, the transaction rolls back and the log entry is not
                    // committed, allowing retry to work
                    $user->notify(new NewCommentNotification($freshComment));
                });
            } catch (Throwable $e) {
                Log::error('Failed to send comment notification', [
                    'comment_id' => $freshComment->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
