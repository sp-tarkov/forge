<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_id
 * @property int $tracking_event_id
 * @property int $moderator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Report $report
 * @property TrackingEvent $trackingEvent
 * @property User $moderator
 */
class ReportAction extends Model
{
    /** @use HasFactory<ReportActionFactory> */
    use HasFactory;

    /**
     * Get the report that this action was taken for.
     *
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the tracking event that recorded this action.
     *
     * @return BelongsTo<TrackingEvent, $this>
     */
    public function trackingEvent(): BelongsTo
    {
        return $this->belongsTo(TrackingEvent::class);
    }

    /**
     * Get the moderator who performed this action.
     *
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
