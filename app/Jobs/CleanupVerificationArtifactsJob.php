<?php

declare(strict_types=1);

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Removes orphaned verification containers and temporary download files left behind on the verification worker when
 * a job is killed before its own cleanup can run. Runs on the verification queue so it executes on the worker that
 * owns the Docker daemon and the temp directory.
 */
#[Timeout(120)]
#[Tries(1)]
final class CleanupVerificationArtifactsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config()->string('verification.queue', 'verification'));
    }

    public function handle(): void
    {
        $this->removeOrphanedContainers();
        $this->removeOrphanedTempFiles();
    }

    /**
     * Force-remove containers carrying the verification label that have been running longer than twice the container
     * timeout.
     */
    private function removeOrphanedContainers(): void
    {
        $listing = Process::timeout(30)->run(sprintf(
            'docker ps --quiet --filter label=%s',
            escapeshellarg(RunVerificationJob::CONTAINER_LABEL),
        ));

        if (! $listing->successful()) {
            return;
        }

        /** @var list<string> $containerIds */
        $containerIds = array_values(array_filter((array) preg_split('/\s+/', mb_trim($listing->output()))));
        if ($containerIds === []) {
            return;
        }

        $cutoff = now()->subSeconds(config()->integer('verification.timeouts.container', 600) * 2);
        $staleIds = $this->filterStaleContainers($containerIds, $cutoff);
        if ($staleIds === []) {
            return;
        }

        Process::timeout(60)->run('docker rm --force '.implode(' ', array_map(escapeshellarg(...), $staleIds)));

        Log::warning('CleanupVerificationArtifactsJob removed orphaned verification containers', [
            'container_ids' => $staleIds,
        ]);
    }

    /**
     * Return the subset of container IDs whose start time is older than the cutoff.
     *
     * @param  list<string>  $containerIds
     * @return list<string>
     */
    private function filterStaleContainers(array $containerIds, CarbonImmutable $cutoff): array
    {
        $inspect = Process::timeout(30)->run(sprintf(
            'docker inspect --format %s %s',
            escapeshellarg('{{.State.StartedAt}}'),
            implode(' ', array_map(escapeshellarg(...), $containerIds)),
        ));

        if (! $inspect->successful()) {
            return [];
        }

        /** @var list<string> $startTimes */
        $startTimes = (array) preg_split('/\R/', mb_trim($inspect->output()));

        $staleIds = [];
        foreach ($containerIds as $index => $containerId) {
            $startedAtRaw = mb_trim($startTimes[$index] ?? '');
            if ($startedAtRaw === '') {
                continue;
            }

            try {
                $startedAt = CarbonImmutable::parse($startedAtRaw);
            } catch (Throwable) {
                continue;
            }

            if ($startedAt->lessThan($cutoff)) {
                $staleIds[] = $containerId;
            }
        }

        return $staleIds;
    }

    /**
     * Delete temporary download files older than twice the maximum job runtime.
     */
    private function removeOrphanedTempFiles(): void
    {
        $tempDir = RunVerificationJob::tempDirectory();
        if (! is_dir($tempDir)) {
            return;
        }

        $cutoffTimestamp = now()->subSeconds(RunVerificationJob::maxRuntime() * 2)->getTimestamp();
        $removed = 0;

        foreach (glob($tempDir.'/'.RunVerificationJob::TEMP_FILE_PREFIX.'*') ?: [] as $file) {
            $modifiedAt = filemtime($file);
            if ($modifiedAt !== false && $modifiedAt < $cutoffTimestamp) {
                @unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            Log::warning('CleanupVerificationArtifactsJob removed orphaned verification temp files', [
                'count' => $removed,
            ]);
        }
    }
}
