<?php

use App\Http\Filters\V1\ModFilter;
use App\Models\Mod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mod::factory()->create(['name' => 'Mod C', 'slug' => 'mod-c', 'featured' => true]);
    Mod::factory()->create(['name' => 'Mod B', 'slug' => 'mod-b', 'featured' => false]);
    Mod::factory()->create(['name' => 'Mod A', 'slug' => 'mod-a', 'featured' => true]);
});

it('can filter mods by id', function () {
    $request = new Request(['id' => '1,2']);
    $filter = new ModFilter($request);
    $query = $filter->apply(Mod::query());

    expect($query->get()->pluck('id')->toArray())->toBe([1, 2]);
});

it('can filter mods by name with wildcard', function () {
    $request = new Request(['name' => 'Mod*']);
    $filter = new ModFilter($request);
    $query = $filter->apply(Mod::query());

    expect($query->get()->pluck('name')->toArray())->toContain('Mod A', 'Mod B', 'Mod C');
});

it('can filter mods by featured status', function () {
    $request = new Request(['featured' => 'true']);
    $filter = new ModFilter($request);
    $query = $filter->apply(Mod::query());

    expect($query->get()->pluck('name')->toArray())->toContain('Mod A', 'Mod C');
});

it('can sort mods by name in ascending order', function () {
    $request = new Request(['sort' => 'name']);
    $filter = new ModFilter($request);
    $query = $filter->apply(Mod::query());

    expect($query->get()->pluck('name')->toArray())->toBe(['Mod A', 'Mod B', 'Mod C']);
});

it('can sort mods by name in descending order', function () {
    $request = new Request(['sort' => '-name']);
    $filter = new ModFilter($request);
    $query = $filter->apply(Mod::query());

    expect($query->get()->pluck('name')->toArray())->toBe(['Mod C', 'Mod B', 'Mod A']);
});
