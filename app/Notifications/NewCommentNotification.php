<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Commentable;
use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

final class NewCommentNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Comment $comment
    ) {}

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{commenter_name?: string, commentable_title?: string, comment_url?: string, comment_body?: string} $data */
        $data = $record->data;

        $commenter = $data['commenter_name'] ?? __('Someone');
        $title = $data['commentable_title'] ?? __('your content');
        $body = $data['comment_body'] ?? '';

        return new NotificationPresentation(
            iconName: 'chat-bubble-left-ellipsis',
            iconColorRole: NotificationColorRole::Blue,
            headline: [
                HeadlineSegment::strong($commenter),
                HeadlineSegment::muted(' '.__('commented on').' '),
                HeadlineSegment::accent(Str::limit($title, 40)),
            ],
            summary: __('commented on').' '.Str::limit($title, 30),
            preview: $body !== '' ? Str::limit($body, 150) : null,
            previewQuoted: true,
            url: $data['comment_url'] ?? null,
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

        if ($notifiable instanceof User && $notifiable->email_comment_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): NotificationMailMessage
    {
        /** @var Commentable<Model>|null $commentable */
        $commentable = $this->comment->commentable;

        throw_if($commentable === null, RuntimeException::class, 'Cannot send notification for comment without commentable.');

        $commentableType = $commentable->getCommentableDisplayName();
        $commentableTitle = $commentable->getTitle();
        $commenterName = $this->comment->user->name;

        $unsubscribeUrl = URL::signedRoute('comment.unsubscribe', [
            'user' => $notifiable->id,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
        ]);

        return (new NotificationMailMessage)
            ->subject('New comment on '.$commentableTitle)
            ->greeting('New comment on '.$commentableTitle)
            ->line(sprintf('**%s** posted a new comment on %s **%s**.', $commenterName, $commentableType, $commentableTitle))
            ->line('')
            ->line('> '.Str::limit($this->comment->body, 500))
            ->action('View Comment', $this->comment->getUrl() ?? '')
            ->footer(sprintf('You can [unsubscribe](%s) from notifications for this %s.', $unsubscribeUrl, $commentableType));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var Commentable<Model>|null $commentable */
        $commentable = $this->comment->commentable;

        throw_if($commentable === null, RuntimeException::class, 'Cannot send notification for comment without commentable.');

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
