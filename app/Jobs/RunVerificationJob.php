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
use App\Support\DataTransferObjects\VerificationCheck;
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

    public const string LOCAL_IMAGE_TAG = 'forge-verification:local';

    public const string TEMP_FILE_PREFIX = 'forge_verify_';

    /**
     * The container output schema version this host understands.
     */
    public const int SUPPORTED_SCHEMA_VERSION = 2;

    /**
     * The most checks kept from a single container run, bounding untrusted output.
     */
    private const int MAX_CHECKS = 100;

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
     * The maximum number of seconds a verification job may run: every stage timeout, including the image build when
     * local image builds are enabled, plus slack for URL validation, archive hashing, and status updates.
     */
    public static function maxRuntime(): int
    {
        $buildTimeout = config()->boolean('verification.build_local_image', false)
            ? config()->integer('verification.timeouts.build', 600)
            : 0;

        return config()->integer('verification.timeouts.download', 900)
            + config()->integer('verification.timeouts.container', 1800)
            + $buildTimeout
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
     * Coerce the container's reported file tree into a bounded list of strings. The container output is untrusted, so
     * non-string entries are dropped and the list is capped host-side independently of what the container reported,
     * with truncation recorded when it happens at either end.
     *
     * @param  array<string, mixed>  $archive
     * @return array{tree: list<string>, truncated: bool}
     */
    private function sanitizeFileTree(array $archive): array
    {
        $rawTree = $archive['file_tree'] ?? [];
        $tree = is_array($rawTree) ? array_values(array_filter($rawTree, is_string(...))) : [];

        $truncated = (bool) ($archive['file_tree_truncated'] ?? false);

        $maxEntries = config()->integer('verification.max_file_tree_entries', 10000);
        if (count($tree) > $maxEntries) {
            $tree = array_slice($tree, 0, $maxEntries);
            $truncated = true;
        }

        return ['tree' => $tree, 'truncated' => $truncated];
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

        $checks = $this->sanitizeChecks($containerData);
        $archiveOk = $this->checksPass($checks);

        /** @var array<string, mixed> $archiveData */
        $archiveData = is_array($containerData['archive'] ?? null) ? $containerData['archive'] : [];
        $fileTreeResult = $this->sanitizeFileTree($archiveData);
        $fileTree = $fileTreeResult['tree'];
        $this->details['file_tree_truncated'] = $fileTreeResult['truncated'];

        $reportedSha256 = $containerData['sha256'] ?? null;
        if (is_string($reportedSha256) && $this->verificationResult->downloaded_sha256 === null) {
            $this->verificationResult->downloaded_sha256 = $reportedSha256;
        }

        $checksVersion = is_string($containerData['checks_version'] ?? null) ? $containerData['checks_version'] : null;

        $finalStatus = $archiveOk ? VerificationStatus::Passed : VerificationStatus::Failed;
        $failureReason = $archiveOk ? null : $this->failureReasonFromChecks($checks);

        $this->verificationResult->update([
            'status' => $finalStatus,
            'archive_ok' => $archiveOk,
            'file_tree' => $fileTree,
            'checks' => array_map(fn (VerificationCheck $check): array => $check->toArray(), $checks),
            'checks_version' => $checksVersion,
            'downloaded_sha256' => $this->verificationResult->downloaded_sha256,
            'failure_reason' => $failureReason,
            'details' => $this->details,
            'completed_at' => now(),
        ]);

        $this->updateVersionStatus($finalStatus);
    }

    /**
     * Coerce the container's reported checks into a bounded list of value objects. The container output is untrusted,
     * so non-array entries are dropped, each entry's fields are sanitized, and the list is capped independently of what
     * the container reported.
     *
     * @param  array<string, mixed>  $containerData
     * @return list<VerificationCheck>
     */
    private function sanitizeChecks(array $containerData): array
    {
        $rawChecks = $containerData['checks'] ?? [];
        if (! is_array($rawChecks)) {
            return [];
        }

        $checks = [];

        foreach ($rawChecks as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $checks[] = VerificationCheck::fromContainer($entry);

            if (count($checks) >= self::MAX_CHECKS) {
                break;
            }
        }

        return $checks;
    }

    /**
     * Whether every enforcing check passed. Report-only checks are ignored, and a skipped check does not fail the run.
     *
     * @param  list<VerificationCheck>  $checks
     */
    private function checksPass(array $checks): bool
    {
        return ! array_any($checks, fn (VerificationCheck $check): bool => $check->isEnforcing() && $check->failed());
    }

    /**
     * Build a failure reason from the enforcing checks that failed.
     *
     * @param  list<VerificationCheck>  $checks
     */
    private function failureReasonFromChecks(array $checks): string
    {
        $reasons = [];

        foreach ($checks as $check) {
            if ($check->isEnforcing() && $check->failed()) {
                $reasons[] = $check->message !== null ? $check->name.': '.$check->message : $check->name;
            }
        }

        return $reasons === [] ? 'Archive verification failed' : implode('; ', $reasons);
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
                ->withUserAgent($safetyService->userAgent())
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
     * Build the verification image from the local docker/verification/ Dockerfile and tag it for the run command.
     * Returns an error message on failure, or null when the image built successfully.
     */
    private function buildLocalImage(): ?string
    {
        $buildStart = microtime(true);

        $command = sprintf(
            'docker build --tag=%s %s',
            escapeshellarg(self::LOCAL_IMAGE_TAG),
            escapeshellarg(base_path('docker/verification')),
        );

        $processResult = Process::timeout(config()->integer('verification.timeouts.build', 600))->run($command);

        $this->details['image_build'] = [
            'duration_seconds' => round(microtime(true) - $buildStart, 2),
        ];

        if (! $processResult->successful()) {
            $stderr = $processResult->errorOutput();

            return 'Docker image build failed: '.($stderr !== '' ? $stderr : 'exit code '.$processResult->exitCode());
        }

        return null;
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
        $pullPolicy = $this->pullPolicy();

        if (config()->boolean('verification.build_local_image', false)) {
            $buildError = $this->buildLocalImage();
            if ($buildError !== null) {
                return ['ok' => false, 'error' => $buildError];
            }

            $dockerImage = self::LOCAL_IMAGE_TAG;
            $pullPolicy = 'never';
        }

        $timeout = config()->integer('verification.timeouts.container', 1800);
        $maxExtractionRatio = config()->integer('verification.max_extraction_ratio', 100);
        $maxExtractedSize = config()->integer('verification.max_extracted_size', 2 * 1024 * 1024 * 1024);
        $maxFileTreeEntries = config()->integer('verification.max_file_tree_entries', 10000);

        chmod($this->tempFilePath, 0644);

        $this->containerName = 'forge-verify-'.$this->verificationResult->id;
        $this->removeContainer();

        $command = sprintf(
            'docker run --rm --pull=%s --init --cap-drop=ALL --security-opt=no-new-privileges --pids-limit=%s --name=%s --label=%s --network=none --memory=512m --cpus=1 -v %s:/input/archive:ro -e ARCHIVE_EXTENSION=%s -e ARCHIVE_SIZE=%s -e MAX_EXTRACTION_RATIO=%s -e MAX_EXTRACTED_SIZE=%s -e MAX_FILE_TREE_ENTRIES=%s %s',
            escapeshellarg($pullPolicy),
            escapeshellarg((string) config()->integer('verification.container.pids_limit', 256)),
            escapeshellarg((string) $this->containerName),
            escapeshellarg(self::CONTAINER_LABEL),
            escapeshellarg((string) $this->tempFilePath),
            escapeshellarg($extension),
            escapeshellarg((string) $archiveSize),
            escapeshellarg((string) $maxExtractionRatio),
            escapeshellarg((string) $maxExtractedSize),
            escapeshellarg((string) $maxFileTreeEntries),
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

        if (($data['schema_version'] ?? null) !== self::SUPPORTED_SCHEMA_VERSION) {
            return ['ok' => false, 'error' => 'Unsupported verification container schema version'];
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
