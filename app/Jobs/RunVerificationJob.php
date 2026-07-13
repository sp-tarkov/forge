<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VerificationStatus;
use App\Exceptions\DownloadSizeExceededException;
use App\Exceptions\VerificationFailedException;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Services\Verification\DownloadSafetyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Downloads a mod/addon archive on the host, then runs an ephemeral Docker container to extract it and report the file
 * tree. The container has no network access and is destroyed after each job.
 */
#[Backoff([30, 60])]
#[Tries(2)]
#[UniqueFor(3600)]
final class RunVerificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string CONTAINER_LABEL = 'forge-verification';

    public const string TEMP_FILE_PREFIX = 'forge_verify_';

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout;

    /** @var array<string, mixed> */
    private array $details = [];

    private ?string $tempFilePath = null;

    private ?string $containerName = null;

    private ?string $validatedIp = null;

    public function __construct(
        public VerificationResult $verificationResult,
    ) {
        $this->onQueue(config()->string('verification.queue', 'verification'));
        $this->timeout = self::maxRuntime();
    }

    /**
     * The maximum number of seconds a verification job may run: both stage timeouts plus slack for URL validation,
     * archive hashing, and status updates.
     */
    public static function maxRuntime(): int
    {
        return config()->integer('verification.timeouts.download', 900)
            + config()->integer('verification.timeouts.container', 600)
            + 120;
    }

    /**
     * The directory where downloaded archives are temporarily stored.
     */
    public static function tempDirectory(): string
    {
        return storage_path('app/private/verification');
    }

    /**
     * The unique ID for preventing duplicate jobs per verifiable entity.
     */
    public function uniqueId(): string
    {
        return $this->verificationResult->verifiable_type.':'.$this->verificationResult->verifiable_id;
    }

    public function handle(DownloadSafetyService $safetyService): void
    {
        try {
            $this->verificationResult->update([
                'status' => VerificationStatus::Running,
                'started_at' => now(),
            ]);

            $this->validateDownloadUrl($safetyService);
            $this->downloadArchive($safetyService);
            $this->extractAndAnalyze();
        } catch (VerificationFailedException) {
            // Expected failure. Just clean up.
        } catch (Throwable $throwable) {
            $this->markAsError($throwable);

            throw $throwable;
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->cleanup();

        $this->verificationResult->update([
            'status' => VerificationStatus::Error,
            'failure_reason' => $exception?->getMessage() ?? 'Job failed',
            'completed_at' => now(),
        ]);

        $this->updateVersionStatus(VerificationStatus::Error);

        Log::error('RunVerificationJob failed', [
            'verification_result_id' => $this->verificationResult->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Validate the download URL is safe before downloading (SSRF protection, direct link, size pre-check).
     */
    private function validateDownloadUrl(DownloadSafetyService $safetyService): void
    {
        $maxFileSize = config()->integer('verification.max_file_size', 500 * 1024 * 1024);
        $safetyCheck = $safetyService->validate($this->verificationResult->download_url, $maxFileSize);
        $this->details['safety_check'] = $safetyCheck;

        if ($safetyCheck['safe'] === false) {
            $this->markAsFailed(
                failureReason: $safetyCheck['error'] ?? 'URL safety check failed',
                downloadOk: false,
            );
        }

        $this->validatedIp = $safetyCheck['resolved_ip'] ?? null;
    }

    /**
     * Download the archive file to a temporary path on the host.
     */
    private function downloadArchive(DownloadSafetyService $safetyService): void
    {
        $maxFileSize = config()->integer('verification.max_file_size', 500 * 1024 * 1024);

        $downloadStart = microtime(true);
        $outcome = $this->performDownload($this->verificationResult->download_url, $maxFileSize, $safetyService);
        $this->details['download'] = [
            'duration_seconds' => round(microtime(true) - $downloadStart, 2),
            ...$outcome,
        ];

        if ($outcome['ok'] === false) {
            $this->markAsFailed(
                failureReason: is_string($outcome['error'] ?? null) ? $outcome['error'] : 'Download failed',
                downloadOk: false,
            );
        }

        $this->verificationResult->update([
            'download_ok' => true,
            'downloaded_size' => $outcome['size'] ?? null,
            'downloaded_sha256' => $outcome['sha256'] ?? null,
        ]);
    }

    /**
     * Run the ephemeral Docker container to extract the archive and analyze the results.
     */
    private function extractAndAnalyze(): void
    {
        $archiveFormat = $this->detectArchiveFormat();
        if ($archiveFormat === null) {
            $this->markAsFailed(
                failureReason: 'Downloaded file is not a ZIP or 7z archive',
                archiveOk: false,
            );
        }

        $this->details['archive_format'] = $archiveFormat;

        $archiveSize = $this->verificationResult->downloaded_size ?? 0;

        $extractionStart = microtime(true);
        $outcome = $this->runContainer($archiveSize, $archiveFormat);
        $this->details['container'] = [
            'duration_seconds' => round(microtime(true) - $extractionStart, 2),
        ];

        if ($outcome['ok'] === false) {
            $this->markAsFailed(
                failureReason: is_string($outcome['error'] ?? null) ? $outcome['error'] : 'Container failed',
                archiveOk: false,
            );
        }

        /** @var array<string, mixed> $containerData */
        $containerData = $outcome['data'] ?? [];
        $archiveOk = (bool) ($containerData['archive_ok'] ?? false);
        /** @var list<string> $fileTree */
        $fileTree = $containerData['file_tree'] ?? [];

        if (isset($containerData['downloaded_sha256']) && is_string($containerData['downloaded_sha256']) && $this->verificationResult->downloaded_sha256 === null) {
            $this->verificationResult->downloaded_sha256 = $containerData['downloaded_sha256'];
        }

        $finalStatus = $archiveOk ? VerificationStatus::Passed : VerificationStatus::Failed;
        $errorValue = $containerData['error'] ?? null;
        $failureReason = $archiveOk ? null : (is_string($errorValue) ? $errorValue : 'Archive extraction failed');

        $this->verificationResult->update([
            'status' => $finalStatus,
            'archive_ok' => $archiveOk,
            'file_tree' => $fileTree,
            'downloaded_sha256' => $this->verificationResult->downloaded_sha256,
            'failure_reason' => $failureReason,
            'details' => $this->details,
            'completed_at' => now(),
        ]);

        $this->updateVersionStatus($finalStatus);
    }

    /**
     * Mark the verification result as failed and halt further processing.
     */
    private function markAsFailed(string $failureReason, ?bool $downloadOk = null, ?bool $archiveOk = null): never
    {
        $updates = [
            'status' => VerificationStatus::Failed,
            'failure_reason' => $failureReason,
            'details' => $this->details,
            'completed_at' => now(),
        ];

        if ($downloadOk !== null) {
            $updates['download_ok'] = $downloadOk;
        }

        if ($archiveOk !== null) {
            $updates['archive_ok'] = $archiveOk;
        }

        $this->verificationResult->update($updates);
        $this->updateVersionStatus(VerificationStatus::Failed);

        throw new VerificationFailedException($failureReason);
    }

    /**
     * Mark the verification result as errored due to an unexpected exception.
     */
    private function markAsError(Throwable $throwable): void
    {
        $this->verificationResult->update([
            'status' => VerificationStatus::Error,
            'failure_reason' => $throwable->getMessage(),
            'details' => $this->details,
            'completed_at' => now(),
        ]);

        $this->updateVersionStatus(VerificationStatus::Error);
    }

    /**
     * Perform the HTTP download to a temporary file.
     *
     * @return array<string, mixed>
     */
    private function performDownload(string $url, int $maxFileSize, DownloadSafetyService $safetyService): array
    {
        $tempDir = self::tempDirectory();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/'.self::TEMP_FILE_PREFIX.bin2hex(random_bytes(16));
        if (touch($tempFile) === false) {
            return ['ok' => false, 'error' => 'Failed to create temporary file'];
        }

        $this->tempFilePath = $tempFile;

        try {
            $downloadTimeout = config()->integer('verification.timeouts.download', 900);

            $response = Http::connectTimeout(10)
                ->timeout($downloadTimeout)
                ->withOptions([
                    ...$safetyService->requestOptions($url, $this->validatedIp),
                    ...$safetyService->downloadGuards($maxFileSize),
                ])
                ->sink($this->tempFilePath)
                ->get($url);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => 'Download returned HTTP '.$response->status()];
            }

            $fileSize = filesize($this->tempFilePath);
            if ($fileSize === false || $fileSize === 0) {
                return ['ok' => false, 'error' => 'Downloaded file is empty'];
            }

            // Backstop for whatever curl had already buffered when the in-flight guards aborted the transfer.
            if ($fileSize > $maxFileSize) {
                return ['ok' => false, 'error' => new DownloadSizeExceededException($fileSize, $maxFileSize)->getMessage()];
            }

            $sha256 = hash_file('sha256', $this->tempFilePath);

            return [
                'ok' => true,
                'size' => $fileSize,
                'sha256' => $sha256,
            ];
        } catch (DownloadSizeExceededException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable $throwable) {
            return ['ok' => false, 'error' => 'Download failed: '.$throwable->getMessage()];
        }
    }

    /**
     * The image pull policy passed to `docker run --pull`. Defaults to "always" so the worker adopts each freshly
     * built image at the cost of a per-job registry check.
     */
    private function pullPolicy(): string
    {
        $policy = config()->string('verification.container.pull_policy', 'always');

        return in_array($policy, ['always', 'missing', 'never'], true) ? $policy : 'always';
    }

    /**
     * Run the ephemeral Docker container to extract the archive and report the file tree.
     *
     * @return array<string, mixed>
     */
    private function runContainer(int $archiveSize, string $extension): array
    {
        if ($this->tempFilePath === null || ! file_exists($this->tempFilePath)) {
            return ['ok' => false, 'error' => 'No downloaded file available for container'];
        }

        $dockerImage = config()->string('verification.docker_image', 'ghcr.io/sp-tarkov/forge/verification:latest');
        $timeout = config()->integer('verification.timeouts.container', 600);
        $maxExtractionRatio = config()->integer('verification.max_extraction_ratio', 100);
        $maxExtractedSize = config()->integer('verification.max_extracted_size', 2 * 1024 * 1024 * 1024);

        chmod($this->tempFilePath, 0644);

        $this->containerName = 'forge-verify-'.$this->verificationResult->id;
        $this->removeContainer();

        $command = sprintf(
            'docker run --rm --pull=%s --init --cap-drop=ALL --security-opt=no-new-privileges --pids-limit=%s --name=%s --label=%s --network=none --memory=512m --cpus=1 -v %s:/input/archive:ro -e ARCHIVE_EXTENSION=%s -e ARCHIVE_SIZE=%s -e MAX_EXTRACTION_RATIO=%s -e MAX_EXTRACTED_SIZE=%s %s',
            escapeshellarg($this->pullPolicy()),
            escapeshellarg((string) config()->integer('verification.container.pids_limit', 256)),
            escapeshellarg((string) $this->containerName),
            escapeshellarg(self::CONTAINER_LABEL),
            escapeshellarg((string) $this->tempFilePath),
            escapeshellarg($extension),
            escapeshellarg((string) $archiveSize),
            escapeshellarg((string) $maxExtractionRatio),
            escapeshellarg((string) $maxExtractedSize),
            escapeshellarg($dockerImage),
        );

        $processResult = Process::timeout($timeout)->run($command);

        if (! $processResult->successful()) {
            $stderr = $processResult->errorOutput();

            return ['ok' => false, 'error' => 'Docker container failed: '.($stderr !== '' ? $stderr : 'exit code '.$processResult->exitCode())];
        }

        $stdout = mb_trim($processResult->output());
        if ($stdout === '') {
            return ['ok' => false, 'error' => 'Docker container produced no output'];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($stdout, true);
        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'Docker container produced invalid JSON'];
        }

        if (isset($data['error']) && is_string($data['error'])) {
            return ['ok' => false, 'data' => $data, 'error' => $data['error']];
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Detect the archive format from the downloaded file's magic bytes. Returns null when the file is not a
     * recognizable ZIP or 7z archive.
     */
    private function detectArchiveFormat(): ?string
    {
        if ($this->tempFilePath === null || ! file_exists($this->tempFilePath)) {
            return null;
        }

        $handle = fopen($this->tempFilePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $magic = fread($handle, 6);
        fclose($handle);

        if (! is_string($magic)) {
            return null;
        }

        return match (true) {
            str_starts_with($magic, "PK\x03\x04"),
            str_starts_with($magic, "PK\x05\x06"),
            str_starts_with($magic, "PK\x07\x08") => 'zip',
            str_starts_with($magic, "7z\xBC\xAF\x27\x1C") => '7z',
            default => null,
        };
    }

    /**
     * Update the denormalized verification status on the version model.
     */
    private function updateVersionStatus(VerificationStatus $status): void
    {
        /** @var ModVersion|AddonVersion|null $verifiable */
        $verifiable = $this->verificationResult->verifiable;
        $verifiable?->updateQuietly([
            'verification_status' => $status->value,
            'last_verified_at' => now(),
        ]);
    }

    /**
     * Clean up the temporary download file and any container left behind by this run.
     */
    private function cleanup(): void
    {
        if ($this->tempFilePath !== null && file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
            $this->tempFilePath = null;
        }

        $this->removeContainer();
        $this->containerName = null;
    }

    /**
     * Force-remove this run's named container, ignoring failures for containers that no longer exist.
     */
    private function removeContainer(): void
    {
        if ($this->containerName === null) {
            return;
        }

        Process::timeout(30)->run('docker rm --force '.escapeshellarg($this->containerName));
    }
}
