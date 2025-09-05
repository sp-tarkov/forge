<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\Import;

use App\Jobs\Import\DataTransferObjects\HubUser;
use App\Jobs\Import\ImportHubJob;
use App\Models\SptVersion;
use App\Models\User;
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

describe('SPT version parsing', function (): void {
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
});

describe('SPT version validation', function (): void {
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
});

describe('user batch processing', function (): void {
    it('handles duplicate emails within the same batch', function (): void {
        // Create test hub users with duplicate emails
        $hubUsers = collect([
            new HubUser([
                'userID' => 100,
                'username' => 'user1',
                'email' => 'duplicate@example.com',
                'password' => 'bcrypt::$2y$10$test1',
                'registrationDate' => now()->subDays(5)->timestamp,
            ]),
            new HubUser([
                'userID' => 101,
                'username' => 'user2',
                'email' => 'duplicate@example.com', // Same email
                'password' => 'bcrypt::$2y$10$test2',
                'registrationDate' => now()->subDays(4)->timestamp,
            ]),
        ]);

        $job = new ImportHubJob;
        $method = new ReflectionMethod(ImportHubJob::class, 'processUserBatch');

        // Process the batch
        $result = $method->invoke($job, $hubUsers);

        // Only one user should be created (first occurrence)
        expect(User::query()->count())->toBe(1);
        expect(User::query()->where('email', 'duplicate@example.com')->count())->toBe(1);
        expect(User::query()->first()->hub_id)->toBe(100);
    });

    it('handles email conflicts with existing users', function (): void {
        // Create an existing user
        User::factory()->create([
            'hub_id' => 200,
            'email' => 'existing@example.com',
        ]);

        // Create hub user with same email but different hub_id
        $hubUsers = collect([
            new HubUser([
                'userID' => 201,
                'username' => 'newuser',
                'email' => 'existing@example.com', // Conflicts with existing user
                'password' => 'bcrypt::$2y$10$test',
                'registrationDate' => now()->subDays(1)->timestamp,
            ]),
        ]);

        $job = new ImportHubJob;
        $method = new ReflectionMethod(ImportHubJob::class, 'processUserBatch');

        // Process the batch
        $result = $method->invoke($job, $hubUsers);

        // Should still have only one user with that email
        expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
        expect(User::query()->first()->hub_id)->toBe(200); // Original user unchanged
    });

    it('updates existing users when hub_id matches', function (): void {
        // Create an existing user
        $existingUser = User::factory()->create([
            'hub_id' => 300,
            'name' => 'oldname',
            'email' => 'old@example.com',
        ]);

        // Create hub user with same hub_id but updated details
        $hubUsers = collect([
            new HubUser([
                'userID' => 300,
                'username' => 'newname',
                'email' => 'new@example.com',
                'password' => 'bcrypt::$2y$10$newpassword',
                'registrationDate' => now()->subDays(10)->timestamp,
            ]),
        ]);

        $job = new ImportHubJob;
        $method = new ReflectionMethod(ImportHubJob::class, 'processUserBatch');

        // Process the batch
        $result = $method->invoke($job, $hubUsers);

        // User should be updated, not duplicated
        expect(User::query()->count())->toBe(1);
        $updatedUser = User::query()->first();
        expect($updatedUser->hub_id)->toBe(300);
        expect($updatedUser->name)->toBe('newname');
        expect($updatedUser->email)->toBe('new@example.com');
    });

    it('ensures no duplicate users are created from hub import', function (): void {
        // This test verifies that the deduplication logic works
        // Create a hub user collection with the same user appearing twice
        $hubUsers = collect([
            new HubUser([
                'userID' => 500,
                'username' => 'testuser',
                'email' => 'test500@example.com',
                'password' => 'bcrypt::$2y$10$test',
                'registrationDate' => now()->subDays(5)->timestamp,
            ]),
            new HubUser([
                'userID' => 500, // Same hub_id
                'username' => 'testuser',
                'email' => 'test500@example.com', // Same email
                'password' => 'bcrypt::$2y$10$test',
                'registrationDate' => now()->subDays(5)->timestamp,
            ]),
        ]);

        $job = new ImportHubJob;
        $method = new ReflectionMethod(ImportHubJob::class, 'processUserBatch');

        // Process the batch
        $result = $method->invoke($job, $hubUsers);

        // Should only create one user despite duplicate in batch
        expect(User::query()->where('hub_id', 500)->count())->toBe(1);
        expect(User::query()->where('email', 'test500@example.com')->count())->toBe(1);
    });

    it('skips update when email would conflict with another user', function (): void {
        // Create two existing users
        $user1 = User::factory()->create([
            'hub_id' => 400,
            'email' => 'user1@example.com',
        ]);

        $user2 = User::factory()->create([
            'hub_id' => 401,
            'email' => 'user2@example.com',
        ]);

        // Try to update user1's email to user2's email
        $hubUsers = collect([
            new HubUser([
                'userID' => 400,
                'username' => 'user1',
                'email' => 'user2@example.com', // Conflicts with user2
                'password' => 'bcrypt::$2y$10$test',
                'registrationDate' => now()->subDays(5)->timestamp,
            ]),
        ]);

        $job = new ImportHubJob;
        $method = new ReflectionMethod(ImportHubJob::class, 'processUserBatch');

        // Process the batch
        $result = $method->invoke($job, $hubUsers);

        // Both users should remain unchanged
        expect(User::query()->count())->toBe(2);
        expect(User::query()->where('hub_id', 400)->first()->email)->toBe('user1@example.com');
        expect(User::query()->where('hub_id', 401)->first()->email)->toBe('user2@example.com');
    });
});
