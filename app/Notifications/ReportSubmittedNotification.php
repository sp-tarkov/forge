<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Reportable;
use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Report $report
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

        if ($notifiable instanceof User && $notifiable->email_comment_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var Reportable $reportable */
        $reportable = $this->report->reportable;
        $reporterName = $this->report->reporter->name;
        /** @var ReportReason $reason */
        $reason = $this->report->reason;
        $reasonLabel = $reason->label();

        $message = (new MailMessage)
            ->subject('New Content Report: '.$reasonLabel)
            ->line(sprintf('%s has reported %s "%s" for: %s', $reporterName, $reportable->getReportableDisplayName(), $reportable->getReportableTitle(), $reasonLabel));

        if ($this->report->context) {
            $message->line('Additional context: '.$this->report->context);
        }

        $excerpt = $reportable->getReportableExcerpt();
        if ($excerpt) {
            $message->line('Content preview: '.$excerpt);
        }

        return $message->action('Review Report', $reportable->getReportableUrl());
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
