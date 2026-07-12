<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Services\Verification\DownloadSafetyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

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

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc123def456',
        'archive_ok' => true,
        'file_tree' => ['package.json', 'src/mod.ts', 'README.md'],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Passed);
    expect($result->download_ok)->toBeTrue();
    expect($result->archive_ok)->toBeTrue();
    expect($result->file_tree)->toBe(['package.json', 'src/mod.ts', 'README.md']);
    expect($result->failure_reason)->toBeNull();

    $modVersion->refresh();
    expect($modVersion->verification_status)->toBe(VerificationStatus::Passed);
    expect($modVersion->last_verified_at)->not->toBeNull();
});

it('marks result as failed when container reports extraction failure', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("PK\x03\x04fake-archive-content", 200)
    );

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc123',
        'archive_ok' => false,
        'file_tree' => [],
        'error' => 'Failed to extract ZIP archive',
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->archive_ok)->toBeFalse();
});

it('detects 7z format from the downloaded file magic bytes', function (): void {
    Http::fake(fn ($request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Content-Type' => 'application/octet-stream', 'Content-Length' => '1000'])
        : Http::response("7z\xBC\xAF\x27\x1Cfake-archive-content", 200)
    );

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc',
        'archive_ok' => true,
        'file_tree' => ['file.txt'],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.7z',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc',
        'archive_ok' => true,
        'file_tree' => ['file.txt'],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/download/12345',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc',
        'archive_ok' => true,
        'file_tree' => [],
        'error' => null,
    ]);

    Process::fake([
        'docker run *' => Process::result(output: $containerOutput),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
    ]);

    $result = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, '--network=none')
        && str_contains($process->command, '--rm')
        && str_contains($process->command, '--memory=512m'));
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

    new RunVerificationJob($result)->handle(new DownloadSafetyService);

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Failed);
    expect($result->download_ok)->toBeFalse();
    expect($result->failure_reason)->toContain('exceeds maximum');
});
