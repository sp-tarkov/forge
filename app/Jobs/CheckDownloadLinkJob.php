<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Services\Verification\ChangeDetectionService;
use App\Support\DataTransferObjects\ChangeDetectionResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks a single mod/addon version's download link for changes via a HEAD request.
 * Dispatched in bulk by DetectDownloadChangesJob and processed concurrently by Horizon.
 */
#[Timeout(60)]
#[Backoff([5, 10])]
#[Tries(2)]
final class CheckDownloadLinkJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  class-string<ModVersion>|class-string<AddonVersion>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public int $versionId,
    ) {}

    public function handle(ChangeDetectionService $changeDetectionService): void
    {
        /** @var ModVersion|AddonVersion|null $version */
        $version = ($this->modelClass)::query()->find($this->versionId);

        if ($version === null) {
            return;
        }

        $result = $changeDetectionService->check($version);

        if ($result->unreachable) {
            return;
        }

        $this->updateFingerprintIfChanged($version, $result);

        if ($result->changed) {
            VerificationResult::dispatchFor($version, VerificationTrigger::ChangeDetected);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('CheckDownloadLinkJob failed', [
            'model' => $this->modelClass,
            'version_id' => $this->versionId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Only update fingerprint columns when values have actually changed.
     */
    private function updateFingerprintIfChanged(ModVersion|AddonVersion $version, ChangeDetectionResult $result): void
    {
        $updates = [];

        if ($result->contentLength !== null && $result->contentLength !== $version->content_length) {
            $updates['content_length'] = $result->contentLength;
        }

        if ($result->etag !== null && $result->etag !== $version->etag) {
            $updates['etag'] = $result->etag;
        }

        if ($result->lastModified !== null && $result->lastModified !== $version->last_modified_header) {
            $updates['last_modified_header'] = $result->lastModified;
        }

        if ($updates !== []) {
            $version->updateQuietly($updates);
        }
    }
}
