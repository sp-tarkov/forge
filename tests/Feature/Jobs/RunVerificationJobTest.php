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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc123def456',
        'archive_ok' => true,
        'file_tree' => ['package.json', 'src/mod.ts', 'README.md'],
        'error' => null,
    ]);

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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc',
        'archive_ok' => true,
        'file_tree' => ['file.txt'],
        'error' => null,
    ]);

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

    $containerOutput = json_encode([
        'downloaded_sha256' => 'abc',
        'archive_ok' => true,
        'file_tree' => [],
        'error' => null,
    ]);

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

it('removes the named container during cleanup', function (): void {
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
        'docker run *' => Process::result(output: json_encode([
            'downloaded_sha256' => 'abc',
            'archive_ok' => true,
            'file_tree' => [],
            'error' => null,
        ])),
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
        'docker run *' => Process::result(output: json_encode([
            'downloaded_sha256' => 'abc',
            'archive_ok' => true,
            'file_tree' => [],
            'error' => null,
        ])),
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
