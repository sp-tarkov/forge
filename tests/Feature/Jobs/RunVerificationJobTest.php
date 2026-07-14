<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Exceptions\DownloadSizeExceededException;
use App\Jobs\RunVerificationJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Services\Verification\DownloadSafetyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Build a v2 container output carrying a single passing archive_extraction check.
 */
function passingContainerOutput(mixed $fileTree = [], bool $fileTreeTruncated = false, string $sha256 = 'abc'): string
{
    return (string) json_encode([
        'schema_version' => 2,
        'checks_version' => '1',
        'sha256' => $sha256,
        'archive' => [
            'file_tree' => $fileTree,
            'file_tree_truncated' => $fileTreeTruncated,
            'symlinks_removed' => 0,
        ],
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
        ],
        'error' => null,
    ]);
}

/**
 * Build a v2 container output whose enforcing archive_extraction check failed.
 */
function failingContainerOutput(string $message = 'Failed to extract ZIP archive', string $sha256 = 'abc'): string
{
    return (string) json_encode([
        'schema_version' => 2,
        'checks_version' => '1',
        'sha256' => $sha256,
        'archive' => null,
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'failed', 'report_only' => false, 'message' => $message, 'data' => []],
        ],
        'error' => null,
    ]);
}

it('marks result as failed when safety check rejects the url', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
        'trigger' => VerificationTrigger::Manual,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('downloadable file');
});

it('marks result as failed when download returns non-200', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response('Not Found', 404)
    );

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
        'trigger' => VerificationTrigger::Manual,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('HTTP 404');
});

it('marks result as failed when docker container fails', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(
            output: '',
            errorOutput: 'docker: Error response from daemon: image not found',
            exitCode: 125,
        ),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeTrue();
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('Docker container failed');
});

it('marks result as passed when container reports success', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput(['package.json', 'src/mod.ts', 'README.md'])),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->download_ok)->toBeTrue();
    expect($result->archive_ok)->toBeTrue();
    expect($result->file_tree)->toBe(['package.json', 'src/mod.ts', 'README.md']);
    expect($result->checks)->toHaveCount(1);
    expect($result->checks[0]['name'])->toBe('archive_extraction');
    expect($result->checks[0]['status'])->toBe('passed');
    expect($result->checks_version)->toBe('1');
    expect($result->failure_reason)->toBeNull();

    $modVersion->refresh();
    expect($modVersion->verification_status)->toBe(VerificationStatus::Passed);
    expect($modVersion->last_verified_at)->not->toBeNull();
});

it('caps and sanitizes the container-reported file tree', function (): void {
    config()->set('verification.max_file_tree_entries', 5);

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    // The container is untrusted: it reports 8 entries, two of which are non-strings, past a host cap of 5.
    $reported = ['a.txt', 'b.txt', 42, 'c.txt', ['nested'], 'd.txt', 'e.txt', 'f.txt'];

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput($reported)),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->file_tree)->toBe(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt']);
    expect($result->details['file_tree_truncated'])->toBeTrue();
});

it('honours the container truncation flag even when under the host cap', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput(['a.txt', 'b.txt'], fileTreeTruncated: true)),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->file_tree)->toBe(['a.txt', 'b.txt']);
    expect($result->details['file_tree_truncated'])->toBeTrue();
});

it('tolerates a non-array file tree from the container', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput('not-an-array')),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->file_tree)->toBe([]);
});

it('passes the file tree entry cap to the container', function (): void {
    config()->set('verification.max_file_tree_entries', 7500);

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, "-e MAX_FILE_TREE_ENTRIES='7500'"));
});

it('marks result as failed when container reports extraction failure', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $containerOutput = failingContainerOutput('Failed to extract ZIP archive', 'abc123');

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('archive_extraction');
    expect($result->failure_reason)->toContain('Failed to extract ZIP archive');
    expect($result->checks)->toHaveCount(1);
    expect($result->checks[0]['status'])->toBe('failed');
});

it('detects 7z format from the downloaded file magic bytes', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("7z\xBC\xAF\x27\x1Cfake-archive-content", 200)
    );

    $containerOutput = passingContainerOutput(['file.txt']);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.7z',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command) && str_contains($process->command, "ARCHIVE_EXTENSION='7z'"));
});

it('verifies urls without an archive extension via content-disposition and magic bytes', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '1000',
            'Content-Disposition' => 'attachment; filename="mod.zip"',
        ])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $containerOutput = passingContainerOutput(['file.txt']);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/download/12345',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    Process::assertRan(fn ($process): bool => is_string($process->command) && str_contains($process->command, "ARCHIVE_EXTENSION='zip'"));
});

it('marks result as failed when the downloaded file is not a recognized archive', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response('this-is-not-an-archive', 200)
    );

    Process::fake();

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeTrue();
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('not a ZIP or 7z archive');
    Process::assertNothingRan();
});

it('uses docker run with network none flag', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $containerOutput = passingContainerOutput();

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, '--network=none')
        && str_contains($process->command, '--rm')
        && str_contains($process->command, '--memory=512m')
        && str_contains($process->command, sprintf("--name='forge-verify-%d'", $result->id))
        && str_contains($process->command, "--label='forge-verification'"));
});

it('runs the container with the security hardening flags', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, "--pull='always'")
        && str_contains($process->command, '--init')
        && str_contains($process->command, '--cap-drop=ALL')
        && str_contains($process->command, '--security-opt=no-new-privileges')
        && str_contains($process->command, "--pids-limit='256'"));
});

it('uses the configured container pull policy', function (): void {
    config()->set('verification.container.pull_policy', 'missing');

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, "--pull='missing'"));
});

it('falls back to the always pull policy for an unrecognized value', function (): void {
    config()->set('verification.container.pull_policy', 'bogus');

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, "--pull='always'"));
});

it('builds the local image and runs it with pull never when local builds are enabled', function (): void {
    config()->set('verification.build_local_image', true);

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker build *' => Process::result(output: ''),
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_starts_with($process->command, 'docker build')
        && str_contains($process->command, sprintf("--tag='%s'", RunVerificationJob::LOCAL_IMAGE_TAG))
        && str_contains($process->command, base_path('docker/verification')));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_starts_with($process->command, 'docker run')
        && str_contains($process->command, "--pull='never'")
        && str_contains($process->command, RunVerificationJob::LOCAL_IMAGE_TAG));

    expect($result->refresh()->status)->toBe(VerificationStatus::Passed);
});

it('marks result as failed when the local image build fails', function (): void {
    config()->set('verification.build_local_image', true);

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker build *' => Process::result(output: '', errorOutput: 'failed to solve: dockerfile parse error', exitCode: 1),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('Docker image build failed');
    expect($result->failure_reason)->toContain('dockerfile parse error');

    Process::assertDidntRun(fn ($process): bool => is_string($process->command)
        && str_starts_with($process->command, 'docker run'));
});

it('does not build the image when local builds are disabled', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertDidntRun(fn ($process): bool => is_string($process->command)
        && str_starts_with($process->command, 'docker build'));
});

it('uses the configured pids limit for the container', function (): void {
    config()->set('verification.container.pids_limit', 512);

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, "--pids-limit='512'"));
});

it('removes the named container during cleanup', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $containerOutput = passingContainerOutput();

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, 'docker rm --force')
        && str_contains($process->command, sprintf("'forge-verify-%d'", $result->id)));
});

it('pins the download connection to the validated ip and guards redirects', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://93.184.215.14/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->details['safety_check']['resolved_ip'])->toBe('93.184.215.14');
});

it('fails verification when the download certificate cannot be verified', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : throw new ConnectionException('cURL error 60: SSL certificate problem: self-signed certificate')
    );

    Process::fake();

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('SSL certificate problem');
    Process::assertNothingRan();
});

it('rejects an oversized download that understated its length to the head request', function (): void {
    config()->set('verification.max_file_size', 1024);

    // The server passes the HEAD size check by claiming a small body, then serves far more than it declared. The
    // in-flight guards abort this mid-transfer in production; the fake cannot stream, so this covers the backstop.
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '100'])
        : Http::response("PK\x03\x04".str_repeat('A', 8192), 200, ['Content-Length' => '100'])
    );

    Process::fake();

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('exceeds maximum');
    Process::assertNothingRan();
});

it('guards the download against oversized responses in flight', function (): void {
    $downloadOptions = [];

    Http::fake(function ($request, $options) use (&$downloadOptions) {
        if ($request->method() === 'GET') {
            $downloadOptions = $options;

            return Http::response("PK\x03\x04fake-archive-content", 200);
        }

        return Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000']);
    });

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    // The fake cannot stream, so assert the in-flight guards are actually attached to the download request.
    expect($downloadOptions)->toHaveKeys(['on_headers', 'progress']);

    $maxFileSize = config()->integer('verification.max_file_size');
    expect(fn () => $downloadOptions['progress'](0, $maxFileSize + 1))
        ->toThrow(DownloadSizeExceededException::class);
});

it('sends the verifier user agent on the download request', function (): void {
    config()->set('verification.user_agent', 'ForgeVerifier/9.9 (+https://example.test)');

    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: passingContainerOutput()),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    Http::assertSent(fn ($request): bool => $request->method() !== 'GET'
        || $request->hasHeader('User-Agent', 'ForgeVerifier/9.9 (+https://example.test)'));
});

it('computes its timeout from the stage timeouts plus slack', function (): void {
    config()->set('verification.timeouts.download', 900);
    config()->set('verification.timeouts.container', 600);

    $result = VerificationResult::factory()->create();

    expect(new RunVerificationJob($result)->timeout)->toBe(1620);
});

it('rejects file exceeding content-length before downloading', function (): void {
    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => '999999999999',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('exceeds maximum');
});

it('does not fail the verification for a failing report-only check', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $output = json_encode([
        'schema_version' => 2,
        'checks_version' => '2',
        'sha256' => 'abc',
        'archive' => ['file_tree' => ['mod.dll'], 'file_tree_truncated' => false, 'symlinks_removed' => 0],
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
            ['name' => 'manifest_present', 'status' => 'failed', 'report_only' => true, 'message' => 'No manifest found', 'data' => []],
        ],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $output),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->archive_ok)->toBeTrue();
    expect($result->checks)->toHaveCount(2);
    expect($result->checks[1]['name'])->toBe('manifest_present');
    expect($result->checks[1]['status'])->toBe('failed');
    expect($result->checks[1]['report_only'])->toBeTrue();
    expect($result->checks_version)->toBe('2');
});

it('does not fail the verification for a skipped enforcing check', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $output = json_encode([
        'schema_version' => 2,
        'checks_version' => '1',
        'sha256' => 'abc',
        'archive' => ['file_tree' => [], 'file_tree_truncated' => false, 'symlinks_removed' => 0],
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
            ['name' => 'signature_check', 'status' => 'skipped', 'report_only' => false, 'message' => 'Not applicable', 'data' => []],
        ],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $output),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->archive_ok)->toBeTrue();
});

it('fails the verification for a failing enforcing check alongside a passing one', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $output = json_encode([
        'schema_version' => 2,
        'checks_version' => '1',
        'sha256' => 'abc',
        'archive' => ['file_tree' => [], 'file_tree_truncated' => false, 'symlinks_removed' => 0],
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
            ['name' => 'forbidden_files', 'status' => 'failed', 'report_only' => false, 'message' => 'Contains an executable', 'data' => []],
        ],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $output),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('forbidden_files: Contains an executable');
});

it('marks result as failed for an unsupported container schema version', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    Process::fake([
        'docker run *' => Process::result(output: (string) json_encode([
            'schema_version' => 99,
            'checks' => [],
        ])),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->archive_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('schema version');
});

it('sanitizes malformed and unknown-status checks from the container', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $output = json_encode([
        'schema_version' => 2,
        'checks_version' => '1',
        'sha256' => 'abc',
        'archive' => ['file_tree' => [], 'file_tree_truncated' => false, 'symlinks_removed' => 0],
        'checks' => [
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
            'not-an-array',
            ['name' => 'mystery', 'status' => 'weird', 'report_only' => true, 'message' => null, 'data' => []],
        ],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $output),
        'docker rm *' => Process::result(output: ''),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create(['link' => 'https://example.com/mod.zip']);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(resolve(DownloadSafetyService::class));

    $result->refresh();

    // The string entry is dropped; the unknown status resolves to a failure, but it is report-only so the run passes.
    expect($result->checks)->toHaveCount(2);
    expect($result->checks[1]['name'])->toBe('mystery');
    expect($result->checks[1]['status'])->toBe('failed');
    expect($result->status)->toBe(VerificationStatus::Passed);
});
