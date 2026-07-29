<?php

declare(strict_types=1);

use App\Http\Resources\Api\V0\ModVersionResource;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;

beforeEach(function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();

    $this->mod = Mod::factory()->create();
    $this->version = ModVersion::factory()->create([
        'mod_id' => $this->mod->id,
        'spt_version_constraint' => '3.8.0',
    ]);
});

it('serializes every field of a version hydrated by the mod version query builder', function (): void {
    $version = new ModVersionQueryBuilder($this->mod->id)->apply()->findOrFail($this->version->id);

    $data = new ModVersionResource($version)->resolve(request());

    expect($data)->toHaveKeys([
        'id', 'hub_id', 'version', 'description', 'link', 'content_length',
        'spt_version_constraint', 'downloads', 'fika_compatibility',
        'published_at', 'created_at', 'updated_at',
    ]);
});

it('limits the response to the requested fields plus the required ones', function (): void {
    $version = new ModVersionQueryBuilder($this->mod->id)->withFields(['link'])->apply()->findOrFail($this->version->id);

    $data = new ModVersionResource($version)->resolve(request()->merge(['fields' => 'link']));

    expect(array_keys($data))->toEqualCanonicalizing(['id', 'version', 'link']);
});
