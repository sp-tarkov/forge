<?php

declare(strict_types=1);

use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SPT version latest minor detection', function (): void {
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
});

describe('SPT version latest minor versions retrieval', function (): void {
    it('returns all patch versions for the latest minor release', function (): void {
        // Create versions for different minor releases
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.10.1']);
        SptVersion::factory()->create(['version' => '3.10.2']);

        // Create the latest minor release with multiple patches
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1']);
        SptVersion::factory()->create(['version' => '3.11.2']);
        SptVersion::factory()->create(['version' => '3.11.3']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(4);
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.3', '3.11.2', '3.11.1', '3.11.0']);
    });

    it('returns single version when latest minor has only one patch', function (): void {
        // Create versions for older minor release
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.10.1']);

        // Create the latest minor release with only one patch
        SptVersion::factory()->create(['version' => '4.0.0']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(1);
        expect($latestMinorVersions->first()->version)->toBe('4.0.0');
    });

    it('excludes version 0.0.0 from results', function (): void {
        SptVersion::factory()->create(['version' => '0.0.0']);
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(2);
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.1', '3.11.0']);
    });

    it('orders versions with release versions before pre-release versions', function (): void {
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1-beta']);
        SptVersion::factory()->create(['version' => '3.11.1']);
        SptVersion::factory()->create(['version' => '3.11.2-alpha']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(4);
        // Ordered by patch desc, then release versions before pre-release versions
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.2-alpha', '3.11.1', '3.11.1-beta', '3.11.0']);
    });

    it('returns empty collection when no versions exist', function (): void {
        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(0);
    });
});
