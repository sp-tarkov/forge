<?php

use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('resolves spt version when mod version is created', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    $modVersion->refresh();

    expect($modVersion->resolved_spt_version_id)->not->toBeNull();
    expect($modVersion->sptVersion->version)->toBe('1.1.1');
});

it('resolves spt version when constraint is updated', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    expect($modVersion->resolved_spt_version_id)->not->toBeNull();
    expect($modVersion->sptVersion->version)->toBe('1.1.1');

    $modVersion->spt_version_constraint = '~1.2.0';
    $modVersion->save();

    $modVersion->refresh();

    expect($modVersion->resolved_spt_version_id)->not->toBeNull();
    expect($modVersion->sptVersion->version)->toBe('1.2.0');
});

it('resolves spt version when spt version is created', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    expect($modVersion->resolved_spt_version_id)->not->toBeNull();
    expect($modVersion->sptVersion->version)->toBe('1.1.1');

    SptVersion::factory()->create(['version' => '1.1.2']);

    $modVersion->refresh();

    expect($modVersion->sptVersion->version)->toBe('1.1.2');
});

it('resolves spt version when spt version is deleted', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    $sptVersion = SptVersion::factory()->create(['version' => '1.1.2']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    expect($modVersion->resolved_spt_version_id)->not->toBeNull();
    expect($modVersion->sptVersion->version)->toBe('1.1.2');

    $sptVersion->delete();
    $modVersion->refresh();

    expect($modVersion->sptVersion->version)->toBe('1.1.1');
});

it('includes only published mod versions', function () {
    $publishedMod = ModVersion::factory()->create([
        'published_at' => Carbon::now()->subDay(),
    ]);
    $unpublishedMod = ModVersion::factory()->create([
        'published_at' => Carbon::now()->addDay(),
    ]);
    $noPublishedDateMod = ModVersion::factory()->create([
        'published_at' => null,
    ]);

    $all = ModVersion::withoutGlobalScopes()->get();
    expect($all)->toHaveCount(3);

    $mods = ModVersion::all();

    expect($mods)->toHaveCount(1)
        ->and($mods->contains($publishedMod))->toBeTrue()
        ->and($mods->contains($unpublishedMod))->toBeFalse()
        ->and($mods->contains($noPublishedDateMod))->toBeFalse();
});

it('handles null published_at as not published', function () {
    $modWithNoPublishDate = ModVersion::factory()->create([
        'published_at' => null,
    ]);

    $mods = ModVersion::all();

    expect($mods->contains($modWithNoPublishDate))->toBeFalse();
});
