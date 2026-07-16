<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationCheckStatus;
use App\Enums\VerificationCheckType;
use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Observers\VerificationResultObserver;
use App\Support\DataTransferObjects\VerificationCheck;
use Carbon\CarbonImmutable;
use Database\Factories\VerificationResultFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string $verifiable_type
 * @property int $verifiable_id
 * @property VerificationStatus $status
 * @property VerificationTrigger $trigger
 * @property string $download_url
 * @property bool|null $download_ok
 * @property int|null $downloaded_size
 * @property string|null $downloaded_sha256
 * @property bool|null $archive_ok
 * @property array<int, string>|null $file_tree
 * @property array<int, array<string, mixed>>|null $checks
 * @property string|null $checks_version
 * @property array<string, mixed>|null $details
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model $verifiable
 */
#[ObservedBy([VerificationResultObserver::class])]
#[Table(name: 'verification_results')]
final class VerificationResult extends Model
{
    /** @use HasFactory<VerificationResultFactory> */
    use HasFactory;

    /**
     * Create a pending verification result and dispatch the verification job.
     * Skips dispatch for mod versions that are not eligible for verification, and when an active (non-stale) pending
     * or running verification already exists for this version.
     */
    public static function dispatchFor(ModVersion|AddonVersion $version, VerificationTrigger $trigger): ?self
    {
        if ($version instanceof ModVersion && ! $version->isEligibleForVerification()) {
            return null;
        }

        $hasActive = self::query()
            ->where('verifiable_type', $version::class)
            ->where('verifiable_id', $version->id)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where('status', VerificationStatus::Pending)
                            ->where('updated_at', '>=', now()->subMinutes(config()->integer('verification.stale.pending_minutes', 1440)));
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', VerificationStatus::Running)
                            ->where('updated_at', '>=', now()->subMinutes(config()->integer('verification.stale.running_minutes', 90)));
                    });
            })
            ->exists();

        if ($hasActive) {
            return null;
        }

        $result = self::query()->create([
            'verifiable_type' => $version::class,
            'verifiable_id' => $version->id,
            'status' => VerificationStatus::Pending,
            'trigger' => $trigger,
            'download_url' => $version->link,
        ]);

        dispatch(new RunVerificationJob($result))
            ->onQueue(config()->string('verification.queue', 'verification'))
            ->afterCommit();

        return $result;
    }

    /**
     * The polymorphic relationship to the parent model (ModVersion or AddonVersion).
     *
     * @return MorphTo<Model, $this>
     */
    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the run's checks as value objects for display, led by the host-side file download check whenever a download
     * outcome was recorded. A failed run that recorded no container checks after a successful download synthesizes a
     * failed archive extraction check, so every failure renders in the same style as a normal check.
     *
     * @return list<VerificationCheck>
     */
    public function displayChecks(): array
    {
        $checks = array_values(array_map(
            VerificationCheck::fromContainer(...),
            $this->checks ?? []
        ));

        if ($checks === [] && $this->status === VerificationStatus::Failed && $this->download_ok !== false) {
            $checks = [new VerificationCheck(
                name: VerificationCheckType::ArchiveExtraction->value,
                status: VerificationCheckStatus::Failed,
                reportOnly: false,
                message: $this->failure_reason,
            )];
        }

        if ($this->download_ok === null) {
            return $checks;
        }

        return [$this->fileDownloadCheck(), ...$checks];
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
            'verifiable_id' => 'integer',
            'status' => VerificationStatus::class,
            'trigger' => VerificationTrigger::class,
            'download_ok' => 'boolean',
            'downloaded_size' => 'integer',
            'archive_ok' => 'boolean',
            'file_tree' => 'array',
            'checks' => 'array',
            'details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Build the host-side file download check from the run's download outcome.
     */
    private function fileDownloadCheck(): VerificationCheck
    {
        $downloadFailed = $this->download_ok === false;

        return new VerificationCheck(
            name: VerificationCheckType::FileDownload->value,
            status: $downloadFailed ? VerificationCheckStatus::Failed : VerificationCheckStatus::Passed,
            reportOnly: false,
            message: $downloadFailed ? $this->failure_reason : null,
        );
    }
}
