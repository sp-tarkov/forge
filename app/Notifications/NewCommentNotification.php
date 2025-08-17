<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class NewCommentNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Comment $comment
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var Commentable<Model> $commentable */
        $commentable = $this->comment->commentable;
        $commentableType = $commentable->getCommentableDisplayName();
        $commentableTitle = $commentable->getTitle();
        $commenterName = $this->comment->user->name;

        // Create unsubscribe URL
        $unsubscribeUrl = URL::signedRoute('comment.unsubscribe', [
            'user' => $notifiable->id,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
        ]);

        return (new MailMessage)
            ->subject('New comment on '.$commentableTitle)
            ->line(sprintf('%s posted a new comment on %s "%s".', $commenterName, $commentableType, $commentableTitle))
            ->line($this->comment->body)
            ->action('View Comment', $this->comment->getUrl())
            ->line('If you no longer wish to receive notifications for this '.$commentableType.', you can unsubscribe using the link below.')
            ->action('Unsubscribe', $unsubscribeUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var Commentable<Model> $commentable */
        $commentable = $this->comment->commentable;

        return [
            'comment_id' => $this->comment->id,
            'comment_body' => $this->comment->body,
            'commenter_name' => $this->comment->user->name,
            'commenter_id' => $this->comment->user->id,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
            'commentable_title' => $commentable->getTitle(),
            'comment_url' => $this->comment->getUrl(),
        ];
    }
}
