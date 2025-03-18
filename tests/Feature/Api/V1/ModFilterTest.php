<?php

declare(strict_types=1);

use App\Http\Filters\V1\ModFilter;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->create(['id' => 1, 'name' => 'Mod C', 'slug' => 'mod-c', 'featured' => true]);
    Mod::factory()->create(['id' => 2, 'name' => 'Mod B', 'slug' => 'mod-b', 'featured' => false]);
    Mod::factory()->create(['id' => 3, 'name' => 'Mod A', 'slug' => 'mod-a', 'featured' => true]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });
});

it('shows valid mods', function (): void {
    $request = new Request;
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->count())->toBe(3);
});

it('does not show mods without versions', function (): void {
    $mod = Mod::factory()->create();

    $request = new Request;
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('id')->toArray())->not()->toContain($mod->id);
});

it('does not show disabled mods', function (): void {
    $mod = Mod::factory()->disabled()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $request = new Request;
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('id')->toArray())->not()->toContain($mod->id);
});

it('does not show mods that do not have any versions which resolve to an SPT version', function (): void {
    SptVersion::factory()->create(['version' => '9.9.9']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']); // SPT version does not exist

    $request = new Request;
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('id')->toArray())->not()->toContain($mod->id);
});

it('can filter mods by id', function (): void {
    $request = new Request(['id' => '1,2']);
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('id')->toArray())->toContain(1, 2);
});

it('can filter mods by name with wildcard', function (): void {
    $request = new Request(['name' => 'Mod*']);
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('name')->toArray())->toContain('Mod A', 'Mod B', 'Mod C');
});

it('can filter mods by featured status', function (): void {
    $request = new Request(['featured' => 'true']);
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('name')->toArray())->toContain('Mod A', 'Mod C');
});

it('can sort mods by name in ascending order', function (): void {
    $request = new Request(['sort' => 'name']);
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('name')->toArray())->toBe(['Mod A', 'Mod B', 'Mod C']);
});

it('can sort mods by name in descending order', function (): void {
    $request = new Request(['sort' => '-name']);
    $filter = new ModFilter($request);
    $builder = $filter->apply(Mod::query());

    expect($builder->get()->pluck('name')->toArray())->toBe(['Mod C', 'Mod B', 'Mod A']);
});
