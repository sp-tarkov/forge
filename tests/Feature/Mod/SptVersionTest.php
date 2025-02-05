<?php

declare(strict_types=1);

use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it("returns true if the version is part of the latest version's minor releases", function (): void {
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);
    $version = SptVersion::factory()->create(['version' => '1.3.0']);
    SptVersion::factory()->create(['version' => '1.3.2']);
    SptVersion::factory()->create(['version' => '1.3.3']);

    expect($version->isLatestMinor())->toBeTrue();
});

it("returns false if the version is not part of the latest version's minor releases", function (): void {
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);
    $version = SptVersion::factory()->create(['version' => '1.2.1']);
    SptVersion::factory()->create(['version' => '1.3.2']);
    SptVersion::factory()->create(['version' => '1.3.3']);

    expect($version->isLatestMinor())->toBeFalse();
});

it('returns false if there is no latest version in the database', function (): void {
    $version = SptVersion::factory()->make(['version' => '1.0.0']);

    expect($version->isLatestMinor())->toBeFalse();
});
