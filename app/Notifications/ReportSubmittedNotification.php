<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Presentable;
use App\Contracts\Reportable;
use App\Enums\NotificationColorRole;
use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Messages\NotificationMailMessage;
use App\Support\DataTransferObjects\HeadlineSegment;
use App\Support\DataTransferObjects\NotificationPresentation;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class ReportSubmittedNotification extends Notification implements Presentable, ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Report $report
    ) {}

    public static function presentDatabaseNotification(DatabaseNotification $record): NotificationPresentation
    {
        /** @var array{reporter_name?: string, reportable_title?: string, reportable_url?: string, reason_label?: string, context?: ?string} $data */
        $data = $record->data;

        $reporter = $data['reporter_name'] ?? __('Someone');
        $reasonLabel = Str::lower($data['reason_label'] ?? __('content'));
        $reportableTitle = $data['reportable_title'] ?? __('content');
        $context = $data['context'] ?? null;

        return new NotificationPresentation(
            iconName: 'exclamation-triangle',
            iconColorRole: NotificationColorRole::Red,
            headline: [
                HeadlineSegment::strong($reporter),
                HeadlineSegment::muted(' '.__('reported').' '),
                HeadlineSegment::accent($reasonLabel),
            ],
            summary: __('reported').' '.Str::limit($reportableTitle, 30),
            preview: $context !== null && $context !== '' ? Str::limit($context, 150) : null,
            previewQuoted: true,
            url: $data['reportable_url'] ?? null,
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
    public function toMail(object $notifiable): NotificationMailMessage
    {
        /** @var Reportable $reportable */
        $reportable = $this->report->reportable;
        $reporterName = $this->report->reporter->name;
        /** @var ReportReason $reason */
        $reason = $this->report->reason;
        $reasonLabel = $reason->label();

        $subject = 'New Content Report: '.$reasonLabel;

        $message = (new NotificationMailMessage)
            ->subject($subject)
            ->greeting($subject)
            ->line(sprintf('%s has reported %s "%s" for: %s', $reporterName, $reportable->getReportableDisplayName(), $reportable->getReportableTitle(), $reasonLabel));

        if ($this->report->context) {
            $message->line('Additional context: '.$this->report->context);
        }

        $excerpt = $reportable->getReportableExcerpt();
        if ($excerpt) {
            $message->line('Content preview: '.$excerpt);
        }

        return $message
            ->action('Review Report', $reportable->getReportableUrl());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var Reportable $reportable */
        $reportable = $this->report->reportable;
        /** @var ReportReason $reason */
        $reason = $this->report->reason;

        return [
            'report_id' => $this->report->id,
            'reporter_name' => $this->report->reporter->name,
            'reporter_id' => $this->report->reporter->id,
            'reason' => $reason->value,
            'reason_label' => $reason->label(),
            'context' => $this->report->context,
            'reportable_type' => $this->report->reportable_type,
            'reportable_id' => $this->report->reportable_id,
            'reportable_title' => $reportable->getReportableTitle(),
            'reportable_excerpt' => $reportable->getReportableExcerpt(),
            'reportable_url' => $reportable->getReportableUrl(),
        ];
    }
}
