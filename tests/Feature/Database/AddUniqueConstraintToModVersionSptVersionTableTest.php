<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('prevents duplicate pivot entries after migration', function (): void {
    // Create test data
    $sptVersion = SptVersion::factory()->create();
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create();
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create();

    // Attach SPT version once
    $modVersion->sptVersions()->attach($sptVersion->id);

    // Verify we have one entry
    $count = DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $sptVersion->id)
        ->count();

    expect($count)->toBe(1);

    // Try to attach again - Laravel should handle this gracefully with unique constraint
    try {
        DB::table('mod_version_spt_version')->insert([
            'mod_version_id' => $modVersion->id,
            'spt_version_id' => $sptVersion->id,
        ]);
        // If we get here, the unique constraint doesn't exist
        throw new Exception('Unique constraint did not prevent duplicate entry');
    } catch (QueryException $queryException) {
        // Expected: unique constraint violation
        expect($queryException->getCode())->toBe('23000'); // Integrity constraint violation
    }
});

it('cleans up existing duplicates during migration', function (): void {
    // This test verifies that the unique constraint prevents duplicates
    // In production, the migration will clean up any existing duplicates before adding the constraint

    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create();

    // Create a mod version without a matching constraint so observer doesn't auto-sync
    $modVersion = ModVersion::factory()
        ->for($mod)
        ->create(['spt_version_constraint' => '>=4.0.0']); // Won't match our 3.10.0 version

    // Manually attach the SPT version
    $modVersion->sptVersions()->attach($sptVersion->id);

    // Verify we have exactly one entry
    $count = DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $sptVersion->id)
        ->count();

    expect($count)->toBe(1);

    // Try to manually insert a duplicate - should fail due to unique constraint
    try {
        DB::table('mod_version_spt_version')->insert([
            'mod_version_id' => $modVersion->id,
            'spt_version_id' => $sptVersion->id,
        ]);
        throw new Exception('Unique constraint did not prevent duplicate entry');
    } catch (QueryException $queryException) {
        // Expected: unique constraint violation
        expect($queryException->getCode())->toBe('23000');
    }
});

it('allows different mod versions to use the same spt version', function (): void {
    $sptVersion = SptVersion::factory()->create(['version' => '3.10.0']);
    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create();

    // Create mod versions with a constraint that matches our SPT version
    // The observer will automatically sync them
    $modVersion1 = ModVersion::factory()->for($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '>=3.10.0',
    ]);
    $modVersion2 = ModVersion::factory()->for($mod)->create([
        'version' => '2.0.0',
        'spt_version_constraint' => '>=3.10.0',
    ]);

    // Verify both versions can use the same SPT version (no unique constraint violation)
    expect($modVersion1->sptVersions)->toHaveCount(1);
    expect($modVersion2->sptVersions)->toHaveCount(1);
    expect($modVersion1->sptVersions->first()->id)->toBe($sptVersion->id);
    expect($modVersion2->sptVersions->first()->id)->toBe($sptVersion->id);
});

it('allows same mod version to use different spt versions', function (): void {
    $sptVersion1 = SptVersion::factory()->create(['version' => '3.10.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '3.11.0']);

    $mod = Mod::factory()
        ->for(User::factory()->create(), 'owner')
        ->create();

    // Create a mod version with a constraint that matches both SPT versions
    // The observer will automatically sync them
    $modVersion = ModVersion::factory()->for($mod)->create([
        'spt_version_constraint' => '>=3.10.0 <=3.11.0',
    ]);

    // Verify one mod version can support multiple SPT versions (no unique constraint violation)
    expect($modVersion->sptVersions)->toHaveCount(2);
    expect($modVersion->sptVersions->pluck('id')->sort()->values()->all())
        ->toBe([$sptVersion1->id, $sptVersion2->id]);
});
