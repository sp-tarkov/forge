<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Presentable;
use App\Enums\NotificationColorRole;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Mchev\Banhammer\Models\Ban;

final class UserBannedNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Ban $ban
    ) {}

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{reason?: ?string, is_permanent?: bool} $data */
        $data = $record->data;

        $isPermanent = (bool) ($data['is_permanent'] ?? false);
        $reason = $data['reason'] ?? null;
        $duration = $isPermanent ? __('permanent') : __('temporary');

        return new NotificationPresentation(
            iconName: 'no-symbol',
            iconColorRole: NotificationColorRole::Red,
            headline: [
                HeadlineSegment::strong(__('Account suspension')),
                HeadlineSegment::muted(' - '),
                HeadlineSegment::accent($duration),
            ],
            summary: $isPermanent ? __('Permanent suspension') : __('Temporary suspension'),
            preview: $reason !== null && $reason !== '' ? $reason : null,
            previewQuoted: true,
        );
    }

    /**
     * Get the notification's delivery channels.
     *
     * This notification always sends both database and email notifications,
     * regardless of the user's email notification preferences. Ban notifications
     * are critical and must reach the user.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): NotificationMailMessage
    {
        $appName = config()->string('app.name');

        /** @var Carbon|null $expiredAt */
        $expiredAt = $this->ban->getAttribute('expired_at');
        $isPermanent = $expiredAt === null;

        /** @var string|null $comment */
        $comment = $this->ban->getAttribute('comment');

        $subject = 'Your account has been suspended';

        $message = (new NotificationMailMessage)
            ->subject($subject)
            ->greeting($subject)
            ->line(sprintf('Your %s account has been suspended with the following details:', $appName));

        if ($isPermanent) {
            $message->line('**Duration:** Permanent');
        } else {
            $message->line('**Duration:** Until '.$expiredAt->format('F j, Y \a\t g:i A T'));
        }

        if ($comment) {
            $message->line('**Reason:** '.$comment);
        }

        $message->line('');

        if ($isPermanent) {
            $message->line('This suspension is permanent. If you believe this was done in error, please contact us for assistance.');
        } else {
            $message->line('Your access will be automatically restored when the suspension period ends.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var Carbon|null $expiredAt */
        $expiredAt = $this->ban->getAttribute('expired_at');

        /** @var Carbon|null $createdAt */
        $createdAt = $this->ban->getAttribute('created_at');

        return [
            'ban_id' => $this->ban->getAttribute('id'),
            'reason' => $this->ban->getAttribute('comment'),
            'expired_at' => $expiredAt?->toIso8601String(),
            'is_permanent' => $expiredAt === null,
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
