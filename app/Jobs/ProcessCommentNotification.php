<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Commentable;
use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
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
     * Track users who have been notified in this run to prevent duplicates.
     *
     * @var Collection<int, int>
     */
    private Collection $notifiedUserIds;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comment $comment
    ) {
        $this->notifiedUserIds = collect();
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

        // Get the commentable
        /** @var Commentable<Model>|null $commentable */
        $commentable = $freshComment->commentable;

        if ($commentable === null) {
            return;
        }

        // Reset notified users for this run
        $this->notifiedUserIds = collect();

        // Step 1: Handle direct reply notification (if this is a reply to another comment)
        $this->handleReplyNotification($freshComment);

        // Step 2: Handle page subscription notifications
        $this->handleSubscriberNotifications($freshComment, $commentable);
    }

    /**
     * Handle sending a reply notification to the parent comment author.
     */
    private function handleReplyNotification(Comment $comment): void
    {
        // Only send reply notification if this comment has a parent
        if ($comment->parent_id === null) {
            return;
        }

        $parentComment = $comment->parent;
        if ($parentComment === null) {
            return;
        }

        $parentAuthor = $parentComment->user;
        if ($parentAuthor === null) {
            return;
        }

        // Don't notify if the parent author is the same as the comment author
        if ($parentAuthor->id === $comment->user_id) {
            return;
        }

        // Don't notify if reply notifications are disabled for this user
        if (! $parentAuthor->email_reply_notifications_enabled) {
            // Still send database notification
            $this->sendReplyDatabaseNotification($comment, $parentAuthor);

            return;
        }

        // Check if already notified for this comment
        if (NotificationLog::hasBeenSent($comment, $parentAuthor->id, CommentReplyNotification::class)) {
            // Mark as notified so we don't send duplicate NewCommentNotification
            $this->notifiedUserIds->push($parentAuthor->id);

            return;
        }

        try {
            DB::transaction(function () use ($parentAuthor, $comment): void {
                $notificationType = $parentAuthor->email_reply_notifications_enabled
                    ? NotificationType::ALL
                    : NotificationType::DATABASE;

                // Record the notification log entry first
                NotificationLog::recordSent(
                    $comment,
                    $parentAuthor->id,
                    CommentReplyNotification::class,
                    $notificationType
                );

                // Send the reply notification
                $parentAuthor->notify(new CommentReplyNotification($comment));
            });

            // Mark this user as notified so they don't get a duplicate NewCommentNotification
            $this->notifiedUserIds->push($parentAuthor->id);
        } catch (Throwable $throwable) {
            Log::error('Failed to send comment reply notification', [
                'comment_id' => $comment->id,
                'parent_author_id' => $parentAuthor->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send only database notification for reply (when email is disabled).
     */
    private function sendReplyDatabaseNotification(Comment $comment, User $user): void
    {
        if (NotificationLog::hasBeenSent($comment, $user->id, CommentReplyNotification::class)) {
            $this->notifiedUserIds->push($user->id);

            return;
        }

        try {
            DB::transaction(function () use ($user, $comment): void {
                NotificationLog::recordSent(
                    $comment,
                    $user->id,
                    CommentReplyNotification::class,
                    NotificationType::DATABASE
                );

                $user->notify(new CommentReplyNotification($comment));
            });

            $this->notifiedUserIds->push($user->id);
        } catch (Throwable $throwable) {
            Log::error('Failed to send comment reply database notification', [
                'comment_id' => $comment->id,
                'user_id' => $user->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle sending notifications to page subscribers.
     *
     * @param  Commentable<Model>  $commentable
     */
    private function handleSubscriberNotifications(Comment $comment, Commentable $commentable): void
    {
        $subscribers = $commentable->getSubscribers();

        // Filter out:
        // 1. The comment author (they shouldn't be notified of their own comment)
        // 2. Users who have already been notified (e.g., via reply notification)
        $subscribers = $subscribers->filter(fn (User $user): bool => $user->id !== $comment->user_id
            && $this->notifiedUserIds->doesntContain($user->id));

        if ($subscribers->isEmpty()) {
            return;
        }

        // Filter out users who have already been notified for this comment
        $subscribersToNotify = $subscribers->reject(fn (User $user): bool => NotificationLog::hasBeenSent(
            $comment,
            $user->id,
            NewCommentNotification::class
        ));

        if ($subscribersToNotify->isEmpty()) {
            return;
        }

        foreach ($subscribersToNotify as $user) {
            if (NotificationLog::hasBeenSent($comment, $user->id, NewCommentNotification::class)) {
                continue;
            }

            try {
                DB::transaction(function () use ($user, $comment): void {
                    $notificationType = $user->email_comment_notifications_enabled
                        ? NotificationType::ALL
                        : NotificationType::DATABASE;

                    // Record the notification log entry first
                    NotificationLog::recordSent(
                        $comment,
                        $user->id,
                        NewCommentNotification::class,
                        $notificationType
                    );

                    // Send the notification - if this fails, the transaction rolls back and the log entry is not
                    // committed, allowing retry to work
                    $user->notify(new NewCommentNotification($comment));
                });
            } catch (Throwable $e) {
                Log::error('Failed to send comment notification', [
                    'comment_id' => $comment->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
