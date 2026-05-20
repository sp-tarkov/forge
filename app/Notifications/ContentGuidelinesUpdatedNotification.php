<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Models\User;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class ContentGuidelinesUpdatedNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{title?: string, body?: string, url?: string} $data */
        $data = $record->data;

        $title = $data['title'] ?? __('Announcement');
        $body = $data['body'] ?? '';

        return new NotificationPresentation(
            iconName: 'megaphone',
            iconColorRole: NotificationColorRole::Amber,
            headline: [HeadlineSegment::accent($title)],
            summary: Str::limit($body, 60),
            preview: $body !== '' ? Str::limit($body, 200) : null,
            previewQuoted: false,
            url: $data['url'] ?? null,
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

        if ($notifiable instanceof User
            && $notifiable->hasVerifiedEmail()
            && $notifiable->email_announcement_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): NotificationMailMessage
    {
        $guidelinesUrl = route('static.content-guidelines');
        $unsubscribeUrl = URL::signedRoute('announcement.unsubscribe', ['user' => $notifiable->id]);
        $preferencesUrl = route('profile.show');

        return (new NotificationMailMessage)
            ->subject('Content Guidelines Updated')
            ->greeting('Content Guidelines Updated')
            ->line('The "AI-Generated Content Policy" section now requires that any usage of LLM-based assistance, including but not limited to code completion, code generation, text generation, and image generation, be disclosed by enabling the "Contains AI Content" flag in mod properties.')
            ->action('Read the Updated Guidelines', $guidelinesUrl)
            ->line('Please review the full guidelines to make sure your existing and future submissions remain compliant.')
            ->footer(sprintf('You can [unsubscribe](%s) from administrative emails, or [manage all of your email preferences](%s).', $unsubscribeUrl, $preferencesUrl));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Content Guidelines Updated',
            'body' => 'Our Content Guidelines have been updated. The AI-Generated Content Policy now requires the "Contains AI Content" flag for any LLM-assisted content.',
            'url' => route('static.content-guidelines'),
        ];
    }
}
