<?php

declare(strict_types=1);

use App\Http\Resources\Api\V0\ModResource;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Support\Api\V0\QueryBuilder\ModDependencyTreeQueryBuilder;
use App\Support\Api\V0\QueryBuilder\ModQueryBuilder;

beforeEach(function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();

    $this->mod = Mod::factory()->create(['hub_id' => 4242]);
    ModVersion::factory()->create(['mod_id' => $this->mod->id, 'spt_version_constraint' => '3.8.0']);
});

it('serializes every field of a mod hydrated by the mod query builder', function (): void {
    $mod = (new ModQueryBuilder)->apply()->findOrFail($this->mod->id);

    $data = new ModResource($mod)->resolve(request());

    expect($data)->toHaveKeys(['id', 'hub_id', 'guid', 'name', 'slug', 'downloads', 'owner', 'additional_authors'])
        ->and($data['hub_id'])->toBe(4242);
});

it('omits fields outside the contract of a narrower hydrating query builder', function (): void {
    $mod = (new ModDependencyTreeQueryBuilder)->apply()->findOrFail($this->mod->id);

    $data = new ModResource($mod)->hydratedBy(ModDependencyTreeQueryBuilder::class)->resolve(request());

    expect($data)->toHaveKeys(['id', 'guid', 'name', 'slug'])
        ->and($data['id'])->toBe($this->mod->id)
        ->and($data)->not->toHaveKey('hub_id')
        ->and($data)->not->toHaveKey('downloads')
        ->and($data)->not->toHaveKey('teaser')
        ->and($data)->not->toHaveKey('created_at');
});

it('keeps honoring the requested fields parameter', function (): void {
    $mod = (new ModQueryBuilder)->withFields(['name'])->apply()->findOrFail($this->mod->id);

    $data = new ModResource($mod)->resolve(request()->merge(['fields' => 'name']));

    expect($data)->toHaveKeys(['id', 'name'])
        ->and($data)->not->toHaveKey('hub_id');
});
