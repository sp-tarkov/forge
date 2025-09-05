<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\Import;

use App\Jobs\Import\ImportHubJob;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create some SPT versions for testing
    SptVersion::factory()->create(['version' => '3.7.0']);
    SptVersion::factory()->create(['version' => '3.7.1']);
    SptVersion::factory()->create(['version' => '3.7.2']);
    SptVersion::factory()->create(['version' => '3.8.0']);
    SptVersion::factory()->create(['version' => '3.8.1']);
    SptVersion::factory()->create(['version' => '3.9.0']);
    SptVersion::factory()->create(['version' => '3.9.5']);
    SptVersion::factory()->create(['version' => '3.10.0']);
    SptVersion::factory()->create(['version' => '3.11.0']);
    SptVersion::factory()->create(['version' => '3.11.1']);
});

it('parses SPT version from description with explicit version number', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        // Full versions with patch - no tilde
        'Made updates for SPT v3.7.0 compatibility.' => '3.7.0',
        'Updated for SPT 3.8.1' => '3.8.1',
        'Compatible with SPT v3.9.0' => '3.9.0',
        'Works with 3.10.0' => '3.10.0',
        'For SPT version 3.7.1' => '3.7.1',
        'Fixed issues with 3.9.5' => '3.9.5',
        'Now supports 3.7.2!' => '3.7.2',
        'Tested on 3.8.0 and working' => '3.8.0',
        'Built for version 3.11.1' => '3.11.1',
        'Requires 3.10.0 or higher' => '3.10.0',

        // Major.minor only - with tilde
        'SPT 3.11 Compatibility' => '~3.11.0',
        'Updated for 3.8' => '~3.8.0',
        'Works with 3.7' => '~3.7.0',
        'Compatible with 3.9' => '~3.9.0',
    ];

    foreach ($testCases as $description => $expectedConstraint) {
        $result = $method->invoke($job, $description, $availableVersions);
        expect($result)->toBe($expectedConstraint, 'Failed for: '.$description);
    }
});

it('parses SPT version with .x notation', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        'This version should be compatible with any SPT v3.8 release.' => '~3.8.0',
        'Now works with Version 3.9.x' => '~3.9.0',
        'Compatible with 3.11.x' => '~3.11.0',
        'For SPT 3.7.X' => '~3.7.0',
    ];

    foreach ($testCases as $description => $expectedConstraint) {
        $result = $method->invoke($job, $description, $availableVersions);
        expect($result)->toBe($expectedConstraint, 'Failed for: '.$description);
    }
});

it('returns null when no SPT version is found in description', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        'This is a general update with bug fixes.',
        'Performance improvements and optimization.',
        'Added new features to the mod.',
        'Fixed various issues reported by users.',
        'Mod version 1.2.3 released', // Should ignore mod's own version
        'Plugin version 2.0.0 update', // Should ignore plugin versions
        'Updated to version 12.11.0', // Unrealistic SPT version
        'Now supports 151.2.0', // Way too high version
        'Compatible with 8.0.0', // Too high for SPT
        'Works with 1.19.4', // Too low for SPT
        '',
    ];

    foreach ($testCases as $description) {
        $result = $method->invoke($job, $description, $availableVersions);
        expect($result)->toBeNull('Failed for: '.$description);
    }
});

it('handles multiple SPT versions in description and returns the first valid one', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    $availableVersions = SptVersion::allValidVersions();

    $description = 'Updated from SPT 3.7.0 to SPT 3.8.0 compatibility';
    $result = $method->invoke($job, $description, $availableVersions);

    // Should return one of the versions found (implementation may vary)
    expect($result)->toBeIn(['3.7.0', '3.8.0']);
});

it('handles case insensitive SPT mentions', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        'spt 3.9.0 compatibility' => '3.9.0',  // Full version - no tilde
        'SPT 3.9.0 COMPATIBILITY' => '3.9.0',
        'Spt V3.9.0 Support' => '3.9.0',
        'AKI 3.9.0 compatibility' => '3.9.0',
        'spt 3.9 compatibility' => '~3.9.0',   // Major.minor - with tilde
        'SPT 3.11 SUPPORT' => '~3.11.0',
    ];

    foreach ($testCases as $description => $expectedConstraint) {
        $result = $method->invoke($job, $description, $availableVersions);
        expect($result)->toBe($expectedConstraint, 'Failed for: '.$description);
    }
});

it('returns null for versions that do not exist in the database', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'parseSptVersionFromDescription');

    // Use limited available versions
    $availableVersions = ['3.9.0', '3.10.0'];

    // Test with a version that's mentioned but not available
    $description = 'Compatible with SPT 3.12.0';
    $result = $method->invoke($job, $description, $availableVersions);

    // Should return null since 3.12.0 doesn't exist in available versions
    expect($result)->toBeNull();

    // Test with a version that does exist (full version)
    $description2 = 'Compatible with SPT 3.9.0';
    $result2 = $method->invoke($job, $description2, $availableVersions);

    // Should return exact constraint since patch version is specified
    expect($result2)->toBe('3.9.0');

    // Test with major.minor only
    $description3 = 'Compatible with SPT 3.9';
    $result3 = $method->invoke($job, $description3, $availableVersions);

    // Should return tilde constraint for major.minor
    expect($result3)->toBe('~3.9.0');
});

it('validates SPT constraints from hub tags', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'validateSptConstraint');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        // Valid constraints that should remain unchanged
        '3.7.0' => '3.7.0',
        '3.8.1' => '3.8.1',
        '~3.9.0' => '~3.9.0',
        '~3.11.0' => '~3.11.0',

        // Invalid exact versions that don't exist should become tilde constraints
        '3.7.99' => '~3.7.0',  // Non-existent patch version
        '3.8.99' => '~3.8.0',  // Non-existent patch version

        // Invalid major.minor combinations should return null
        '3.1.0' => null,   // 3.1.x doesn't exist in our test data
        '~3.1.0' => null,  // 3.1.x doesn't exist in our test data
        '3.6.0' => null,   // 3.6.x doesn't exist in our test data
        '~3.6.0' => null,  // 3.6.x doesn't exist in our test data
        '5.0.0' => null,   // Way too high
        '~5.0.0' => null,  // Way too high
    ];

    foreach ($testCases as $constraint => $expectedValidated) {
        $result = $method->invoke($job, $constraint, $availableVersions);
        expect($result)->toBe($expectedValidated, 'Failed for constraint: '.$constraint);
    }
});

it('normalizes version strings correctly', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'normalizeVersionString');

    $testCases = [
        '3.9.0' => '3.9.0',
        '3.9' => '3.9',
        '3.9.x' => '3.9',
        '3.9.X' => '3.9',
        'invalid' => null,
        '3.9.0.1' => null, // Too many parts
        'v3.9.0' => null, // Has prefix
        '3..9' => null, // Invalid format
    ];

    foreach ($testCases as $input => $expected) {
        $result = $method->invoke($job, $input);
        expect($result)->toBe($expected, 'Failed for: '.$input);
    }
});

it('correctly matches versions against actual SPT versions', function (): void {
    $job = new ImportHubJob;
    $method = new ReflectionMethod(ImportHubJob::class, 'matchesSptVersion');

    $availableVersions = SptVersion::allValidVersions();

    $testCases = [
        // Versions that should match (exist in our test SPT versions)
        '3.7.0' => true,
        '3.7.1' => true,
        '3.8.0' => true,
        '3.8.1' => true,
        '3.9.0' => true,
        '3.9.5' => true,
        '3.10.0' => true,
        '3.11.0' => true,
        '3.11.1' => true,
        '3.7' => true,    // Should match 3.7.x versions
        '3.8' => true,    // Should match 3.8.x versions
        '3.9' => true,    // Should match 3.9.x versions
        '3.11' => true,   // Should match 3.11.x versions

        // Versions that should NOT match (don't exist)
        '1.2.3' => false,
        '2.0.0' => false,
        '4.0.0' => false,
        '5.0.0' => false,
        '8.0.0' => false,
        '12.11.0' => false,
        '151.2.0' => false,
    ];

    foreach ($testCases as $version => $expected) {
        $result = $method->invoke($job, $version, $availableVersions);
        expect($result)->toBe($expected, sprintf('Failed for version: %s', $version));
    }
});
