<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use Carbon\CarbonImmutable;
use Database\Factories\VerificationResultFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
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
 * @property array<string, mixed>|null $details
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model $verifiable
 */
#[Table(name: 'verification_results')]
final class VerificationResult extends Model
{
    /** @use HasFactory<VerificationResultFactory> */
    use HasFactory;

    /**
     * The polymorphic relationship to the parent model (ModVersion or AddonVersion).
     *
     * @return MorphTo<Model, $this>
     */
    /**
     * Create a pending verification result and dispatch the verification job.
     * Skips dispatch if a pending or running verification already exists for this version.
     */
    public static function dispatchFor(ModVersion|AddonVersion $version, VerificationTrigger $trigger): ?self
    {
        $hasPending = self::query()
            ->where('verifiable_type', $version::class)
            ->where('verifiable_id', $version->id)
            ->whereIn('status', [VerificationStatus::Pending, VerificationStatus::Running])
            ->exists();

        if ($hasPending) {
            return null;
        }

        $result = self::query()->create([
            'verifiable_type' => $version::class,
            'verifiable_id' => $version->id,
            'status' => VerificationStatus::Pending,
            'trigger' => $trigger,
            'download_url' => $version->link,
        ]);

        dispatch(new RunVerificationJob($result))->onQueue(config()->string('verification.queue', 'verification'));

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
            'details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
