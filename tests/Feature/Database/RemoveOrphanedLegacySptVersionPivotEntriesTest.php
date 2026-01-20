<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Services\SptVersionService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    // Create the legacy 0.0.0 SPT version
    $this->legacySptVersion = SptVersion::factory()->create(['version' => '0.0.0']);

    // Create a valid SPT version for comparison
    $this->validSptVersion = SptVersion::factory()->create(['version' => '3.10.0']);

    // Create a mod owner
    $this->owner = User::factory()->create();
});

it('removes orphaned pivot entries linked to 0.0.0 with non-matching constraints', function (): void {
    $mod = Mod::factory()->for($this->owner, 'owner')->create();

    // Create a mod version with a constraint that doesn't resolve to 0.0.0
    // Use a constraint that won't match any SPT version to prevent observer auto-sync
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create(['spt_version_constraint' => '~3.6.0']); // No 3.6.x versions exist

    // Manually insert an orphaned pivot entry (simulating legacy data)
    DB::table('mod_version_spt_version')->insert([
        'mod_version_id' => $modVersion->id,
        'spt_version_id' => $this->legacySptVersion->id,
        'pinned_to_spt_publish' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify the orphaned entry exists
    expect(DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $this->legacySptVersion->id)
        ->exists())->toBeTrue();

    // Run the migration logic
    $sptVersionService = resolve(SptVersionService::class);
    $sptVersionService->resolve($modVersion);

    // Verify the orphaned entry was removed
    expect(DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $this->legacySptVersion->id)
        ->exists())->toBeFalse();

    // Verify no SPT versions are linked (constraint doesn't match any)
    expect($modVersion->fresh()->sptVersions)->toHaveCount(0);
});

it('removes orphaned pivot entries with empty constraints', function (): void {
    $mod = Mod::factory()->for($this->owner, 'owner')->create();

    // Create a mod version with empty constraint (database default)
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create(['spt_version_constraint' => '']);

    // Manually insert an orphaned pivot entry (simulating legacy data)
    DB::table('mod_version_spt_version')->insert([
        'mod_version_id' => $modVersion->id,
        'spt_version_id' => $this->legacySptVersion->id,
        'pinned_to_spt_publish' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify the orphaned entry exists
    expect(DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $this->legacySptVersion->id)
        ->exists())->toBeTrue();

    // Run the migration logic
    $sptVersionService = resolve(SptVersionService::class);
    $sptVersionService->resolve($modVersion);

    // Verify the orphaned entry was removed
    expect(DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $this->legacySptVersion->id)
        ->exists())->toBeFalse();

    expect($modVersion->fresh()->sptVersions)->toHaveCount(0);
});

it('preserves valid SPT version links for resolvable constraints', function (): void {
    $mod = Mod::factory()->for($this->owner, 'owner')->create();

    // Create a mod version with a constraint that matches our valid SPT version
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create(['spt_version_constraint' => '^3.10.0']);

    // The observer should have linked this to 3.10.0
    expect($modVersion->fresh()->sptVersions)->toHaveCount(1);
    expect($modVersion->fresh()->sptVersions->first()->version)->toBe('3.10.0');

    // Re-resolve to ensure nothing changes
    $sptVersionService = resolve(SptVersionService::class);
    $sptVersionService->resolve($modVersion);

    // Verify the link is still there
    expect($modVersion->fresh()->sptVersions)->toHaveCount(1);
    expect($modVersion->fresh()->sptVersions->first()->version)->toBe('3.10.0');
});
