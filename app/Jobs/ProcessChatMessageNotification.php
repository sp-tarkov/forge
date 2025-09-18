<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\NotificationLog;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessChatMessageNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message
    ) {
        // Delay job by 5 minutes to allow for message deletion/editing
        $this->delay(now()->addMinutes(5));
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        // Reload the message to check if it still exists
        $freshMessage = Message::query()->find($this->message->id);

        if (! $freshMessage) {
            return;
        }

        $conversation = $freshMessage->conversation;
        $sender = $freshMessage->user;

        // Get the recipient of the message (the other user in the conversation)
        $recipient = $conversation->getOtherUser($sender);

        if (! $recipient) {
            return;
        }

        // Check if recipient wants chat notifications (global setting)
        if (! $recipient->email_chat_notifications_enabled) {
            return;
        }

        // Check conversation-specific notification preference
        if (! $conversation->isNotificationEnabledForUser($recipient)) {
            return;
        }

        // Get all unread messages in this conversation for the recipient
        // that were sent after the last notification was sent
        $lastNotificationTime = NotificationLog::query()
            ->where('user_id', $recipient->id)
            ->where('notification_class', NewChatMessageNotification::class)
            ->where('notifiable_type', Conversation::class)
            ->where('notifiable_id', $conversation->id)
            ->latest('updated_at')
            ->value('updated_at');

        $unreadMessagesQuery = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $recipient->id)
            ->unreadBy($recipient)
            ->orderBy('created_at', 'asc');

        if ($lastNotificationTime) {
            $unreadMessagesQuery->where('created_at', '>', $lastNotificationTime);
        }

        $unreadMessages = $unreadMessagesQuery->get();

        if ($unreadMessages->isEmpty()) {
            return;
        }

        // Check if we've sent a notification within the last 5 minutes
        $recentNotification = NotificationLog::query()
            ->where('user_id', $recipient->id)
            ->where('notification_class', NewChatMessageNotification::class)
            ->where('notifiable_type', Conversation::class)
            ->where('notifiable_id', $conversation->id)
            ->where('updated_at', '>', now()->subMinutes(5))
            ->exists();

        if ($recentNotification) {
            return;
        }

        DB::transaction(function () use ($recipient, $conversation, $unreadMessages): void {
            // Send the notification
            $recipient->notify(new NewChatMessageNotification($conversation, $unreadMessages));

            // Record that notification has been sent
            $notificationType = $recipient->email_chat_notifications_enabled
                ? NotificationType::ALL
                : NotificationType::DATABASE;

            // Update or create the notification log
            NotificationLog::query()->updateOrCreate([
                'notifiable_type' => Conversation::class,
                'notifiable_id' => $conversation->id,
                'user_id' => $recipient->id,
                'notification_class' => NewChatMessageNotification::class,
            ], [
                'notification_type' => $notificationType,
                'updated_at' => now(),
            ]);
        });
    }
}
