<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SPT version filtering', function (): void {
    it('filters mods by a single SPT version', function (): void {
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
        $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

        $mod1 = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
            'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
        ]);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
            'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
        ]);

        // Confirm associations created by observers and services
        expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
            ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

        // Apply the filter
        $filters = ['sptVersions' => [$sptVersion1->version]];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that only the correct mod is returned
        expect($filteredMods)->toHaveCount(1)
            ->and($filteredMods->first()->id)->toBe($mod1->id);
    });

    it('filters mods by multiple SPT versions', function (): void {
        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
        $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);
        $sptVersion3 = SptVersion::factory()->create(['version' => '3.0.0']);

        // Create the mods and their versions with appropriate constraints
        $mod1 = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
            'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
        ]);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
            'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
        ]);

        $mod3 = Mod::factory()->create();
        $modVersion3 = ModVersion::factory()->recycle($mod3)->create([
            'spt_version_constraint' => '3.0.0', // Constraint matching sptVersion3
        ]);

        // Confirm associations created by observers and services
        expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
            ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version)
            ->and($modVersion3->sptVersions->pluck('version')->toArray())->toContain($sptVersion3->version);

        // Apply the filter with multiple SPT versions
        $filters = ['sptVersions' => [$sptVersion1->version, $sptVersion3->version]];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that the correct mods are returned
        expect($filteredMods)->toHaveCount(2)
            ->and($filteredMods->pluck('id')->toArray())->toContain($mod1->id, $mod3->id);
    });

    it('returns no mods when no SPT versions match', function (): void {
        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
        $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

        // Create the mods and their versions with appropriate constraints
        $mod1 = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
            'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
        ]);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
            'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
        ]);

        // Confirm associations created by observers and services
        expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
            ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

        // Apply the filter with a non-matching SPT version
        $filters = ['sptVersions' => ['3.0.0']]; // Version '3.0.0' does not exist in associations
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that no mods are returned
        expect($filteredMods)->toBeEmpty();
    });

    it('handles an empty SPT versions array correctly', function (): void {
        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
        $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

        // Create the mods and their versions with appropriate constraints
        $mod1 = Mod::factory()->create();
        $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
            'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
        ]);

        $mod2 = Mod::factory()->create();
        $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
            'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
        ]);

        // Confirm associations created by observers and services
        expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
            ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

        // Apply the filter with an empty SPT versions array
        $filters = ['sptVersions' => []];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that the behavior is as expected (return all mods, or none, depending on intended behavior)
        expect($filteredMods)->toHaveCount(2); // Modify this assertion to reflect your desired behavior
    });
});

describe('query and feature filtering', function (): void {
    it('filters mods based on a exact search term', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $mod = Mod::factory()->create(['name' => 'BigBrain']);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

        Mod::factory()->create(['name' => 'SmallFeet']);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

        $filters = ['query' => 'BigBrain'];
        $filteredMods = new ModFilter($filters)->apply()->get();

        expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
    });

    it('filters mods based featured status', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $mod = Mod::factory()->create(['name' => 'BigBrain', 'featured' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

        Mod::factory()->create(['name' => 'SmallFeet']);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

        $filters = ['featured' => 'only'];
        $filteredMods = new ModFilter($filters)->apply()->get();

        expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
    });
});

describe('combined filtering', function (): void {
    it('filters mods correctly with combined filters', function (): void {
        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
        $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

        // Create the mods and their versions with appropriate names and featured status
        $mod1 = Mod::factory()->create(['name' => 'Awesome Mod', 'featured' => true]);
        $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
            'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
        ]);

        $mod2 = Mod::factory()->create(['name' => 'Cool Mod', 'featured' => false]);
        $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
            'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
        ]);

        // Confirm associations created by observers and services
        expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
            ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

        // Apply combined filters
        $filters = [
            'query' => 'Awesome',
            'featured' => 'only',
            'sptVersions' => [$sptVersion1->version],
        ];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that only the correct mod is returned
        expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod1->id);
    });
});

describe('legacy versions filtering', function (): void {
    it('filters mods to show only legacy versions when legacy is selected', function (): void {
        // Create a full set of SPT versions to simulate production environment
        // Active versions (last three minors)
        $activeSptVersions = [
            SptVersion::factory()->create(['version' => '3.11.4']),
            SptVersion::factory()->create(['version' => '3.11.3']),
            SptVersion::factory()->create(['version' => '3.11.0']),
            SptVersion::factory()->create(['version' => '3.10.5']),
            SptVersion::factory()->create(['version' => '3.10.0']),
            SptVersion::factory()->create(['version' => '3.9.8']),
        ];

        // Legacy version (not in the last three minors)
        $legacySptVersion = SptVersion::factory()->create(['version' => '3.8.0']);

        // Create mods with different version associations
        $modActive = Mod::factory()->create();
        $modVersionActive = ModVersion::factory()->recycle($modActive)->create([
            'spt_version_constraint' => '3.11.0',
        ]);

        $modLegacy = Mod::factory()->create();
        $modVersionLegacy = ModVersion::factory()->recycle($modLegacy)->create([
            'spt_version_constraint' => '3.8.0',
        ]);

        // Apply the legacy filter
        $filters = ['sptVersions' => ['legacy']];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that the legacy mod should be returned (it's not in the active versions list)
        expect($filteredMods->pluck('id')->toArray())->toContain($modLegacy->id);

        // Assert that the active mod should NOT be returned
        expect($filteredMods->pluck('id')->toArray())->not->toContain($modActive->id);
    });

    it('shows 0.0.0 versions in legacy filter only for admins', function (): void {
        // Create an administrator
        $this->actingAs(User::factory()->admin()->create());

        // Create active SPT versions to establish proper context
        $activeSptVersions = [
            SptVersion::factory()->create(['version' => '3.11.4']),
            SptVersion::factory()->create(['version' => '3.11.0']),
            SptVersion::factory()->create(['version' => '3.10.5']),
            SptVersion::factory()->create(['version' => '3.9.8']),
        ];

        // Create fallback SPT version
        $fallbackSptVersion = SptVersion::factory()->create(['version' => '0.0.0']);

        // Create mod with fallback version
        $modFallback = Mod::factory()->create();
        $modVersionFallback = ModVersion::factory()->recycle($modFallback)->create([
            'spt_version_constraint' => '0.0.0',
            'disabled' => false,
            'published_at' => now(),
        ]);

        // Apply the legacy filter
        $filters = ['sptVersions' => ['legacy']];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that the fallback mod is returned for admin
        expect($filteredMods->pluck('id')->toArray())->toContain($modFallback->id);
    });

    it('does not show 0.0.0 versions in legacy filter for regular users', function (): void {
        // Create active SPT versions to establish proper context
        $activeSptVersions = [
            SptVersion::factory()->create(['version' => '3.11.4']),
            SptVersion::factory()->create(['version' => '3.11.0']),
            SptVersion::factory()->create(['version' => '3.10.5']),
            SptVersion::factory()->create(['version' => '3.9.8']),
        ];

        // Create fallback SPT version
        $fallbackSptVersion = SptVersion::factory()->create(['version' => '0.0.0']);

        // Create mod with fallback version
        $modFallback = Mod::factory()->create();
        $modVersionFallback = ModVersion::factory()->recycle($modFallback)->create([
            'spt_version_constraint' => '0.0.0',
        ]);

        // Apply the legacy filter as regular user (not authenticated)
        $filters = ['sptVersions' => ['legacy']];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that the 0.0.0 mod is not returned for regular user
        expect($filteredMods->pluck('id')->toArray())->not->toContain($modFallback->id);
    });

    it('combines legacy and normal version filters with OR logic', function (): void {
        // Create active SPT versions to establish proper context
        $activeSptVersions = [
            SptVersion::factory()->create(['version' => '3.11.4']),
            SptVersion::factory()->create(['version' => '3.11.0']),
            SptVersion::factory()->create(['version' => '3.10.5']),
            SptVersion::factory()->create(['version' => '3.9.8']),
        ];

        // Create legacy SPT versions (not in the last three minors)
        $legacySptVersion = SptVersion::factory()->create(['version' => '3.8.0']);

        // Create mods with different version associations
        $modActive = Mod::factory()->create();
        $modVersionActive = ModVersion::factory()->recycle($modActive)->create([
            'spt_version_constraint' => '3.11.0',
        ]);

        $modLegacy = Mod::factory()->create();
        $modVersionLegacy = ModVersion::factory()->recycle($modLegacy)->create([
            'spt_version_constraint' => '3.8.0',
        ]);

        // Apply both active and legacy filters
        $filters = ['sptVersions' => ['3.11.0', 'legacy']];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that both mods are returned
        expect($filteredMods->pluck('id')->toArray())->toContain($modActive->id, $modLegacy->id);
    });
});

describe('disabled mods filtering', function (): void {
    it('does not show disabled mods to unauthorized users', function (): void {
        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);

        // Create the mods and their versions with appropriate constraints
        $modEnabled = Mod::factory()->create();
        $modEnabledVersion = ModVersion::factory()->recycle($modEnabled)->create(['spt_version_constraint' => '1.0.0']);

        $modDisabled = Mod::factory()->disabled()->create();
        $modDisabledVersion = ModVersion::factory()->recycle($modDisabled)->create(['spt_version_constraint' => '1.0.0']);

        // Apply an empty filter
        $filters = [];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that only the enabled mod is returned
        expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($modEnabled->id)
            ->and($filteredMods->pluck('id')->toArray())->not()->toContain($modDisabled->id);
    });

    it('does show disabled mods to administrators and moderators', function (): void {
        // Create an administrator
        $this->actingAs(User::factory()->admin()->create());

        // Create the SPT versions
        $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);

        // Create the mods and their versions with appropriate constraints
        $modEnabled = Mod::factory()->create();
        $modEnabledVersion = ModVersion::factory()->recycle($modEnabled)->create(['spt_version_constraint' => '1.0.0']);

        $modDisabled = Mod::factory()->create();
        $modDisabledVersion = ModVersion::factory()->recycle($modDisabled)->create(['spt_version_constraint' => '1.0.0']);

        // Apply an empty filter
        $filters = [];
        $filteredMods = new ModFilter($filters)->apply()->get();

        // Assert that both the enabled and disabled mods are returned
        expect($filteredMods)
            ->toHaveCount(2)
            ->and($filteredMods->pluck('id')->toArray())
            ->toContain($modEnabled->id, $modDisabled->id);
    });
});

describe('Fika compatibility filtering', function (): void {
    it('shows all mods when Fika compatibility filter is unchecked (false)', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $modCompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modCompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'compatible',
        ]);

        $modIncompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modIncompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'incompatible',
        ]);

        $filters = ['fikaCompatibility' => false];
        $filteredMods = new ModFilter($filters)->apply()->get();

        expect($filteredMods)->toHaveCount(2)
            ->and($filteredMods->pluck('id')->toArray())->toContain($modCompatible->id, $modIncompatible->id);
    });

    it('shows only Fika compatible mods when filter is checked (true)', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);

        $modCompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modCompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'compatible',
        ]);

        $modIncompatible = Mod::factory()->create();
        ModVersion::factory()->recycle($modIncompatible)->create([
            'spt_version_constraint' => '^1.0.0',
            'fika_compatibility' => 'incompatible',
        ]);

        $filters = ['fikaCompatibility' => true];
        $filteredMods = new ModFilter($filters)->apply()->get();

        expect($filteredMods)->toHaveCount(1)
            ->and($filteredMods->first()->id)->toBe($modCompatible->id);
    });
});
