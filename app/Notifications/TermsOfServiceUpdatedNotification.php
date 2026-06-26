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

final class TermsOfServiceUpdatedNotification extends Notification implements Presentable, ShouldQueue
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
        $termsUrl = route('static.terms');
        $unsubscribeUrl = URL::signedRoute('announcement.unsubscribe', ['user' => $notifiable->id]);
        $preferencesUrl = route('profile.show');

        return (new NotificationMailMessage)
            ->subject('Terms of Service Updated')
            ->greeting('Terms of Service Updated')
            ->line("We have clarified the automated-access section of our Terms of Service. Scraping our website's HTML content is not permitted, while our public API and XML feeds remain open for automated access within reasonable rate limits.")
            ->action('Read the Updated Terms', $termsUrl)
            ->line('Please review the full Terms of Service to make sure your usage remains compliant.')
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
            'title' => 'Terms of Service Updated',
            'body' => 'Our Terms of Service have been updated with a clarified automated-access policy: HTML scraping is prohibited, while the public API and XML feeds remain open to automated tooling.',
            'url' => route('static.terms'),
        ];
    }
}
