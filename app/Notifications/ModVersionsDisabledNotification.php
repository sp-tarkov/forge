<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Models\User;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationDetail;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class ModVersionsDisabledNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    /**
     * @param  array<int, array{mod_name: string, version: string, url: string, reason: string}>  $versions
     */
    public function __construct(public array $versions) {}

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{title?: string, body?: string, versions?: array<int, array{mod_name?: string, version?: string, url?: string, reason?: string}>, url?: string} $data */
        $data = $record->data;

        $body = $data['body'] ?? '';
        $versions = array_values($data['versions'] ?? []);
        $count = count($versions);

        // Surface each affected version as its own linked, annotated line so the dashboard carries the same actionable
        // detail as the email (version, reason, and a link to fix it) rather than a single summary line.
        $details = array_map(
            static fn (array $version): NotificationDetail => new NotificationDetail(
                label: mb_trim(($version['mod_name'] ?? '').' '.($version['version'] ?? '')),
                url: $version['url'] ?? null,
                note: $version['reason'] ?? null,
            ),
            $versions,
        );

        return new NotificationPresentation(
            iconName: 'no-symbol',
            iconColorRole: NotificationColorRole::Amber,
            headline: [HeadlineSegment::accent($count === 1 ? __('1 version unpublished') : __(':count versions unpublished', ['count' => $count]))],
            summary: Str::limit($body, 60),
            preview: __('These versions were hidden because their version numbers cannot be used for dependency matching. Open each to correct its number and re-publish it.'),
            previewQuoted: false,
            url: $data['url'] ?? null,
            details: $details,
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
        $reviewUrl = $notifiable->profile_url.'#mods';
        $preferencesUrl = route('profile.show');
        $unsubscribeUrl = URL::signedRoute('announcement.unsubscribe', ['user' => $notifiable->id]);

        $count = count($this->versions);

        $message = (new NotificationMailMessage)
            ->subject('Mod versions unpublished: invalid version numbers')
            ->greeting('Action needed: invalid version numbers')
            ->line($count === 1
                ? 'One of your mod versions has been unpublished because its version number is not a valid semantic version the site can use for dependency matching:'
                : sprintf('%d of your mod versions have been unpublished because their version numbers are not valid semantic versions the site can use for dependency matching:', $count));

        foreach ($this->versions as $version) {
            $message->line(sprintf('- [%s %s](%s) - %s', $version['mod_name'], $version['version'], $version['url'], $version['reason']));
        }

        return $message
            ->line('Open each version, correct its version number, and re-publish it. The editor now rejects version numbers that cannot be used for dependency matching.')
            ->action('Review your mod versions', $reviewUrl)
            ->footer(sprintf('You can [unsubscribe](%s) from administrative emails, or [manage all of your email preferences](%s).', $unsubscribeUrl, $preferencesUrl));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $count = count($this->versions);

        // The notification's primary click target is the author's own mod listing, where they can see all affected
        // mods together; the per-version edit links live in the detail lines built from `versions`.
        $url = $notifiable instanceof User ? $notifiable->profile_url.'#mods' : route('profile.show');

        return [
            'title' => 'Versions unpublished',
            'body' => $count === 1
                ? 'One of your mod versions was unpublished because its version number is invalid.'
                : sprintf('%d of your mod versions were unpublished because their version numbers are invalid.', $count),
            'versions' => $this->versions,
            'url' => $url,
        ];
    }
}
