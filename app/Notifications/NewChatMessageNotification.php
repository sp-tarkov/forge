<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class NewChatMessageNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, Message>  $unreadMessages
     */
    public function __construct(
        public Conversation $conversation,
        public Collection $unreadMessages
    ) {}

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{sender_name?: string, message_count?: int, conversation_url?: string, latest_message_preview?: string} $data */
        $data = $record->data;

        $sender = $data['sender_name'] ?? __('Someone');
        $count = (int) ($data['message_count'] ?? 1);
        $preview = $data['latest_message_preview'] ?? '';

        if ($count > 1) {
            $headline = [
                HeadlineSegment::strong($sender),
                HeadlineSegment::muted(' '.__('sent you').' '),
                HeadlineSegment::accent(__(':count new messages', ['count' => $count])),
            ];
            $summary = __('sent you :count messages', ['count' => $count]);
        } else {
            $headline = [
                HeadlineSegment::strong($sender),
                HeadlineSegment::muted(' '.__('sent you a').' '),
                HeadlineSegment::accent(__('new message')),
            ];
            $summary = __('sent you a message');
        }

        return new NotificationPresentation(
            iconName: 'chat-bubble-left-right',
            iconColorRole: NotificationColorRole::Purple,
            headline: $headline,
            summary: $summary,
            preview: $preview !== '' ? Str::limit($preview, 150) : null,
            previewQuoted: true,
            url: $data['conversation_url'] ?? null,
        );
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->email_chat_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): NotificationMailMessage
    {
        $sender = $this->conversation->getOtherUser($notifiable);
        $senderName = $sender instanceof User ? $sender->name : 'Someone';
        $messageCount = $this->unreadMessages->count();

        $latestMessage = $this->unreadMessages->last();
        $messagePreview = $latestMessage !== null ? Str::limit($latestMessage->content, 150) : '';

        $unsubscribeUrl = URL::signedRoute('chat.unsubscribe', [
            'user' => $notifiable->id,
            'conversation' => $this->conversation->hash_id,
        ]);

        $subject = $messageCount > 1
            ? sprintf('%d new messages from %s', $messageCount, $senderName)
            : sprintf('New message from %s', $senderName);

        $mailMessage = (new NotificationMailMessage)
            ->subject($subject)
            ->greeting($subject);

        if ($messageCount > 1) {
            $mailMessage->line(sprintf('**%s** sent you %d new messages.', $senderName, $messageCount));

            foreach ($this->unreadMessages->take(3) as $index => $message) {
                if ($index === 0) {
                    $mailMessage->line('');
                }

                $mailMessage->line('> '.Str::limit($message->content, 200));
            }

            if ($messageCount > 3) {
                $mailMessage->line('')
                    ->line(sprintf('*...and %d more %s*',
                        $messageCount - 3,
                        $messageCount - 3 === 1 ? 'message' : 'messages'));
            }
        } else {
            $mailMessage->line(sprintf('**%s** sent you a new message:', $senderName))
                ->line('')
                ->line('> '.$messagePreview);
        }

        return $mailMessage
            ->action('View Conversation', $this->conversation->url)
            ->footer(sprintf('You can [unsubscribe](%s) from notifications for this conversation.', $unsubscribeUrl));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $sender = $this->conversation->getOtherUser($notifiable);
        $messageCount = $this->unreadMessages->count();
        $latestMessage = $this->unreadMessages->last();

        return [
            'conversation_id' => $this->conversation->id,
            'conversation_hash_id' => $this->conversation->hash_id,
            'sender_name' => $sender instanceof User ? $sender->name : 'Someone',
            'sender_id' => $sender?->id,
            'message_count' => $messageCount,
            'latest_message_id' => $latestMessage?->id,
            'latest_message_preview' => $latestMessage !== null ? Str::limit($latestMessage->content, 150) : '',
            'conversation_url' => $this->conversation->url,
        ];
    }
}
