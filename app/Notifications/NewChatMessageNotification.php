<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class NewChatMessageNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, Message>  $unreadMessages
     */
    public function __construct(
        public Conversation $conversation,
        public Collection $unreadMessages
    ) {}

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
    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->conversation->getOtherUser($notifiable);
        $senderName = $sender ? $sender->name : 'Someone';
        $messageCount = $this->unreadMessages->count();

        // Get the most recent message for preview
        $latestMessage = $this->unreadMessages->last();
        $messagePreview = Str::limit($latestMessage->content, 150);

        // Create unsubscribe URL
        $unsubscribeUrl = URL::signedRoute('chat.unsubscribe', [
            'user' => $notifiable->id,
            'conversation' => $this->conversation->hash_id,
        ]);

        $mailMessage = (new MailMessage)
            ->subject($messageCount > 1
                ? sprintf('%d new messages from %s', $messageCount, $senderName)
                : sprintf('New message from %s', $senderName))
            ->greeting('Hello!');

        if ($messageCount > 1) {
            $mailMessage->line(sprintf('**%s** sent you %d new messages.', $senderName, $messageCount));

            // Show message previews with better formatting
            foreach ($this->unreadMessages->take(3) as $index => $message) {
                if ($index === 0) {
                    $mailMessage->line(''); // Add spacing before messages
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
            ->line('')
            ->action('View Conversation', $this->conversation->url)
            ->line('')
            ->line(sprintf('You can [unsubscribe](%s) from notifications for this conversation.', $unsubscribeUrl))
            ->salutation('Regards,  '."\n".config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $sender = $this->conversation->getOtherUser($notifiable);
        $messageCount = $this->unreadMessages->count();
        $latestMessage = $this->unreadMessages->last();

        return [
            'conversation_id' => $this->conversation->id,
            'conversation_hash_id' => $this->conversation->hash_id,
            'sender_name' => $sender ? $sender->name : 'Someone',
            'sender_id' => $sender?->id,
            'message_count' => $messageCount,
            'latest_message_id' => $latestMessage->id,
            'latest_message_preview' => Str::limit($latestMessage->content, 150),
            'conversation_url' => $this->conversation->url,
        ];
    }
}
