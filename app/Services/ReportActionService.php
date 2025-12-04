<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Models\Report;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing moderation actions taken on reports.
 */
class ReportActionService
{
    public function __construct(
        protected TrackService $trackService
    ) {}

    /**
     * Take a moderation action in the context of a report.
     *
     * @param  Report  $report  The report being addressed
     * @param  TrackingEventType  $eventType  The type of action being taken
     * @param  Model  $trackable  The model being acted upon
     * @param  callable  $actionCallback  The actual moderation action to perform
     * @param  bool  $resolveReport  Whether to auto-resolve the report
     * @param  array<string, mixed>  $additionalData  Additional data for tracking
     * @param  string|null  $reason  Optional reason for the moderation action
     */
    public function takeAction(
        Report $report,
        TrackingEventType $eventType,
        Model $trackable,
        callable $actionCallback,
        bool $resolveReport = true,
        array $additionalData = [],
        ?string $reason = null
    ): ReportAction {
        return DB::transaction(function () use ($report, $eventType, $trackable, $actionCallback, $resolveReport, $additionalData, $reason): ReportAction {
            // Execute the actual moderation action
            $actionCallback();

            // Create the tracking event synchronously (marked as moderation action)
            $trackingEvent = $this->trackService->eventSync(
                eventType: $eventType,
                trackable: $trackable,
                additionalData: $additionalData,
                isModerationAction: true,
                reason: $reason
            );

            // Link the action to the report
            $reportAction = ReportAction::query()->create([
                'report_id' => $report->id,
                'tracking_event_id' => $trackingEvent->id,
                'moderator_id' => Auth::id(),
            ]);

            // Optionally mark the report as resolved
            if ($resolveReport) {
                $report->update(['status' => ReportStatus::RESOLVED]);
            }

            return $reportAction;
        });
    }

    /**
     * Link an existing tracking event to a report.
     */
    public function linkExistingAction(
        Report $report,
        TrackingEvent $trackingEvent
    ): ReportAction {
        return ReportAction::query()->create([
            'report_id' => $report->id,
            'tracking_event_id' => $trackingEvent->id,
            'moderator_id' => Auth::id(),
        ]);
    }
}
