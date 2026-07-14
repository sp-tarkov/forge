<?php

declare(strict_types=1);

use App\Jobs\CleanupVerificationArtifactsJob;
use App\Jobs\RunVerificationJob;
use Illuminate\Support\Facades\Process;

it('force-removes containers running longer than twice the container timeout', function (): void {
    Process::fake([
        'docker ps *' => Process::result(output: "abc123\ndef456\n"),
        'docker inspect *' => Process::result(
            output: now()->subMinutes(45)->toIso8601ZuluString()."\n".now()->subMinutes(2)->toIso8601ZuluString(),
        ),
        'docker rm *' => Process::result(output: ''),
    ]);

    new CleanupVerificationArtifactsJob()->handle();

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, 'docker rm --force')
        && str_contains($process->command, 'abc123')
        && ! str_contains($process->command, 'def456'));
});

it('does not remove containers when none are stale', function (): void {
    Process::fake([
        'docker ps *' => Process::result(output: "abc123\n"),
        'docker inspect *' => Process::result(output: now()->subMinutes(2)->toIso8601ZuluString()),
    ]);

    new CleanupVerificationArtifactsJob()->handle();

    Process::assertDidntRun(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, 'docker rm'));
});

it('does not inspect or remove anything when no labelled containers are running', function (): void {
    Process::fake([
        'docker ps *' => Process::result(output: ''),
    ]);

    new CleanupVerificationArtifactsJob()->handle();

    Process::assertDidntRun(fn ($process): bool => is_string($process->command)
        && (str_contains($process->command, 'docker inspect') || str_contains($process->command, 'docker rm')));
});

it('deletes orphaned temp files older than twice the job runtime and keeps fresh ones', function (): void {
    Process::fake([
        'docker ps *' => Process::result(output: ''),
    ]);

    $tempDir = RunVerificationJob::tempDirectory();
    if (! is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $oldFile = $tempDir.'/'.RunVerificationJob::TEMP_FILE_PREFIX.'test_old';
    $freshFile = $tempDir.'/'.RunVerificationJob::TEMP_FILE_PREFIX.'test_fresh';

    touch($oldFile, time() - (3 * 3600));
    touch($freshFile);

    try {
        new CleanupVerificationArtifactsJob()->handle();

        expect(file_exists($oldFile))->toBeFalse();
        expect(file_exists($freshFile))->toBeTrue();
    } finally {
        @unlink($oldFile);
        @unlink($freshFile);
    }
});
