<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AltInvestigationStatus;
use App\Jobs\RunAltDetectionJob;
use App\Support\DataTransferObjects\AltInvestigation;
use Carbon\CarbonImmutable;
use Database\Factories\AltInvestigationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $requested_by
 * @property AltInvestigationStatus $status
 * @property array<array-key, mixed>|null $results
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table(name: 'alt_investigation_runs')]
final class AltInvestigationRun extends Model
{
    /** @use HasFactory<AltInvestigationRunFactory> */
    use HasFactory;

    /**
     * The most recent investigation run for a suspect, or null if none exists.
     */
    public static function latestFor(User $suspect): ?self
    {
        return self::query()
            ->where('user_id', $suspect->id)
            ->latest()
            ->first();
    }

    /**
     * Create a pending investigation run and dispatch its job. Reuses an in-progress run for the same suspect so a
     * duplicate click does not queue the work twice.
     */
    public static function dispatchFor(User $suspect, ?int $requestedBy = null): self
    {
        $inProgress = self::query()
            ->where('user_id', $suspect->id)
            ->whereIn('status', [AltInvestigationStatus::Pending, AltInvestigationStatus::Processing])
            ->latest()
            ->first();

        if ($inProgress instanceof self) {
            return $inProgress;
        }

        $run = self::query()->create([
            'user_id' => $suspect->id,
            'requested_by' => $requestedBy,
            'status' => AltInvestigationStatus::Pending,
        ]);

        dispatch(new RunAltDetectionJob($run));

        return $run;
    }

    /**
     * Rebuild the investigation result from stored JSON, or null while the run has not completed.
     */
    public function result(): ?AltInvestigation
    {
        return $this->results === null ? null : AltInvestigation::fromArray($this->results);
    }

    /**
     * Mark the run as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => AltInvestigationStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Store the completed investigation result.
     */
    public function markCompleted(AltInvestigation $investigation): void
    {
        $this->update([
            'status' => AltInvestigationStatus::Completed,
            'results' => $investigation->toArray(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Record a failed run with its reason.
     */
    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => AltInvestigationStatus::Failed,
            'error' => $reason,
            'completed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === AltInvestigationStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === AltInvestigationStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status === AltInvestigationStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === AltInvestigationStatus::Failed;
    }

    /**
     * Whether the run is still queued or running.
     */
    public function inProgress(): bool
    {
        if ($this->isPending()) {
            return true;
        }

        return $this->isProcessing();
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'requested_by' => 'integer',
            'status' => AltInvestigationStatus::class,
            'results' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
