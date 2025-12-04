<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reporter_id
 * @property int $reportable_id
 * @property string $reportable_type
 * @property ReportReason $reason
 * @property string|null $context
 * @property ReportStatus $status
 * @property int|null $assignee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Model $reportable
 * @property User $reporter
 * @property User|null $assignee
 * @property-read Collection<int, ReportAction> $actions
 * @property-read Collection<int, TrackingEvent> $trackingEvents
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * Get the user who submitted this report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the content that was reported.
     *
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the moderator assigned to handle this report.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get all actions taken for this report.
     *
     * @return HasMany<ReportAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ReportAction::class);
    }

    /**
     * Get the tracking events associated with this report.
     *
     * @return BelongsToMany<TrackingEvent, $this>
     */
    public function trackingEvents(): BelongsToMany
    {
        return $this->belongsToMany(TrackingEvent::class, 'report_actions')
            ->withPivot(['moderator_id', 'note'])
            ->withTimestamps();
    }

    /**
     * Check if the report is currently assigned to someone.
     */
    public function isAssigned(): bool
    {
        return $this->assignee_id !== null;
    }

    /**
     * Check if the report is assigned to a specific user.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->assignee_id === $user->id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
        ];
    }
}
