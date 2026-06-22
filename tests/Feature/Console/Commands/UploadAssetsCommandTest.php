<?php

declare(strict_types=1);

use App\Console\Commands\UploadAssetsCommand;
use Illuminate\Support\Facades\Storage;

it('uploads the ignored-updates static file to R2', function (): void {
    Storage::fake('public');
    Storage::fake('r2');

    Storage::disk('public')->put('check-mods/ignored-updates.json', '{"schemaVersion":1,"ignored":[]}');

    $this->artisan(UploadAssetsCommand::class)
        ->expectsOutputToContain('Uploading static file to: check-mods/ignored-updates.json')
        ->assertSuccessful();

    Storage::disk('r2')->assertExists('check-mods/ignored-updates.json');
    expect(Storage::disk('r2')->get('check-mods/ignored-updates.json'))
        ->toBe('{"schemaVersion":1,"ignored":[]}');
});

it('skips the static file when it is missing locally', function (): void {
    Storage::fake('public');
    Storage::fake('r2');

    $this->artisan(UploadAssetsCommand::class)
        ->expectsOutputToContain('Skipping missing static file: check-mods/ignored-updates.json')
        ->assertSuccessful();

    Storage::disk('r2')->assertMissing('check-mods/ignored-updates.json');
});
