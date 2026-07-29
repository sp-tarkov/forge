<?php

declare(strict_types=1);

use App\Jobs\ResolveSptVersionsJob;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\SptVersionService;
use Illuminate\Support\Facades\DB;

it('attaches the satisfying spt versions for each mod version', function (): void {
    $spt110 = SptVersion::factory()->create(['version' => '1.1.0']);
    $spt120 = SptVersion::factory()->create(['version' => '1.2.0']);
    SptVersion::factory()->create(['version' => '2.0.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^1.0.0']);

    DB::table('mod_version_spt_version')->where('mod_version_id', $modVersion->id)->delete();

    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    expect($modVersion->sptVersions()->pluck('spt_versions.id')->all())
        ->toEqualCanonicalizing([$spt110->id, $spt120->id]);
});

it('removes pivot rows that no longer satisfy the constraint', function (): void {
    $spt110 = SptVersion::factory()->create(['version' => '1.1.0']);
    $spt200 = SptVersion::factory()->create(['version' => '2.0.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^1.0.0']);

    DB::table('mod_version_spt_version')->insert([
        'mod_version_id' => $modVersion->id,
        'spt_version_id' => $spt200->id,
        'pinned_to_spt_publish' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    expect($modVersion->sptVersions()->pluck('spt_versions.id')->all())->toEqual([$spt110->id]);
});

it('preserves pinned_to_spt_publish on pivot rows that remain satisfied', function (): void {
    $spt110 = SptVersion::factory()->create(['version' => '1.1.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^1.0.0']);

    DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $spt110->id)
        ->update(['pinned_to_spt_publish' => true]);

    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    $pinned = DB::table('mod_version_spt_version')
        ->where('mod_version_id', $modVersion->id)
        ->where('spt_version_id', $spt110->id)
        ->value('pinned_to_spt_publish');

    expect($pinned)->toBeTruthy();
});

it('detaches all spt versions when the constraint no longer matches anything', function (): void {
    SptVersion::factory()->create(['version' => '1.1.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^1.0.0']);

    expect($modVersion->sptVersions()->count())->toBe(1);

    $modVersion->updateQuietly(['spt_version_constraint' => '^9.9.9']);

    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    expect($modVersion->sptVersions()->count())->toBe(0);
});

it('skips unpublished mod versions', function (): void {
    SptVersion::factory()->create(['version' => '1.1.0']);

    $modVersion = ModVersion::factory()->create([
        'spt_version_constraint' => '^1.0.0',
        'published_at' => null,
    ]);

    DB::table('mod_version_spt_version')->where('mod_version_id', $modVersion->id)->delete();

    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    expect(DB::table('mod_version_spt_version')->where('mod_version_id', $modVersion->id)->count())->toBe(0);
});
