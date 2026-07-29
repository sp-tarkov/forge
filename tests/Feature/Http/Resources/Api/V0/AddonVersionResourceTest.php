<?php

declare(strict_types=1);

use App\Http\Resources\Api\V0\AddonVersionResource;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Support\Api\V0\QueryBuilder\AddonVersionQueryBuilder;

beforeEach(function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();

    $mod = Mod::factory()->create();
    ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '^3.8.0']);

    $this->addon = Addon::factory()->create(['mod_id' => $mod->id]);
    $this->version = AddonVersion::factory()->create(['addon_id' => $this->addon->id]);
});

it('serializes every field of a version hydrated by the addon version query builder', function (): void {
    $version = new AddonVersionQueryBuilder($this->addon->id)->apply()->findOrFail($this->version->id);

    $data = new AddonVersionResource($version)->resolve(request());

    expect($data)->toHaveKeys([
        'id', 'version', 'link', 'content_length', 'mod_version_constraint',
        'downloads', 'published_at', 'created_at', 'updated_at',
    ]);
});

it('limits the response to the requested fields plus the id', function (): void {
    $version = new AddonVersionQueryBuilder($this->addon->id)
        ->withFields(['link'])
        ->apply()
        ->findOrFail($this->version->id);

    $data = new AddonVersionResource($version)->resolve(request()->merge(['fields' => 'link']));

    expect(array_keys($data))->toEqualCanonicalizing(['id', 'link']);
});
