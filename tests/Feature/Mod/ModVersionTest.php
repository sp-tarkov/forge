<?php

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('resolves spt versions when mod version is created', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    $modVersion->refresh();

    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(2)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');
});

it('resolves spt versions when constraint is updated', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    $modVersion->refresh();

    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(2)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');

    $modVersion->spt_version_constraint = '~1.2.0';
    $modVersion->save();

    $modVersion->refresh();

    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(1)
        ->and($sptVersions->pluck('version'))->toContain('1.2.0');
});

it('resolves spt versions when spt version is created', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    SptVersion::factory()->create(['version' => '1.2.0']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    $modVersion->refresh();

    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(2)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');

    SptVersion::factory()->create(['version' => '1.1.2']);

    $modVersion->refresh();

    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(3)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1', '1.1.2');
});

it('resolves spt versions when spt version is deleted', function () {
    SptVersion::factory()->create(['version' => '1.0.0']);
    SptVersion::factory()->create(['version' => '1.1.0']);
    SptVersion::factory()->create(['version' => '1.1.1']);
    $sptVersion = SptVersion::factory()->create(['version' => '1.1.2']);

    $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

    $modVersion->refresh();
    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(3)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1', '1.1.2');

    $sptVersion->delete();

    $modVersion->refresh();
    $sptVersions = $modVersion->sptVersions;

    expect($sptVersions)->toHaveCount(2)
        ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');
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

it('updates the parent mods updated_at column when updated', function () {
    $originalDate = now()->subDays(10);
    $version = ModVersion::factory()->create(['updated_at' => $originalDate]);

    $version->downloads++;
    $version->save();

    $version->refresh();

    expect($version->mod->updated_at)->not->toEqual($originalDate)
        ->and($version->mod->updated_at->format('Y-m-d'))->toEqual(now()->format('Y-m-d'));
});

it('builds download links using the specified version', function () {
    $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
    $modVersion1 = ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3']);
    $modVersion2 = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0']);
    $modVersion3 = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4']);

    expect($modVersion1->downloadUrl())->toEqual("/mod/download/$mod->id/$mod->slug/$modVersion1->version")
        ->and($modVersion2->downloadUrl())->toEqual("/mod/download/$mod->id/$mod->slug/$modVersion2->version")
        ->and($modVersion3->downloadUrl())->toEqual("/mod/download/$mod->id/$mod->slug/$modVersion3->version");
});

it('increments download counts when downloaded', function () {
    $mod = Mod::factory()->create(['downloads' => 0]);
    $modVersion = ModVersion::factory()->recycle($mod)->create(['downloads' => 0]);

    $request = $this->get($modVersion->downloadUrl());
    $request->assertStatus(307);

    $modVersion->refresh();

    expect($modVersion->downloads)->toEqual(1)
        ->and($modVersion->mod->downloads)->toEqual(1);
});

it('rate limits download links from being hit', function () {
    $mod = Mod::factory()->create(['downloads' => 0]);
    $modVersion = ModVersion::factory()->recycle($mod)->create(['downloads' => 0]);

    // The first 5 requests should be fine.
    for ($i = 0; $i < 5; $i++) {
        $request = $this->get($modVersion->downloadUrl());
        $request->assertStatus(307);
    }

    // The 6th request should be rate limited.
    $request = $this->get($modVersion->downloadUrl());
    $request->assertStatus(429);

    $modVersion->refresh();

    // The download count should still be 5.
    expect($modVersion->downloads)->toEqual(5)
        ->and($modVersion->mod->downloads)->toEqual(5);
});
