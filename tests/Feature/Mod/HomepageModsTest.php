<?php

declare(strict_types=1);

use App\Livewire\Page\Homepage;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::clear();
});

it('should only display featured mods in the featured section', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->count(3)->create(['featured' => true]);
    Mod::factory()->count(3)->create(['featured' => false]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });

    // Assert that the featured mods are the ones that are actually featured
    $featured = Livewire::test(Homepage::class)
        ->assertViewHas('featured', function ($featured) {
            return $featured->every(fn ($mod) => $mod->featured);
        });
});

it('should not display disabled mods', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->count(3)->create(['featured' => true]);
    Mod::factory()->count(3)->disabled()->create(['featured' => false]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });

    $homepage = Livewire::test(Homepage::class)
        ->assertViewHas('featured', function (Collection $featured) {
            return $featured->every(fn (Mod $mod) => $mod->featured);
        })
        ->assertViewHas('featured', function (Collection $featured) {
            return $featured->count() === 3;
        })
        ->assertViewHas('newest', function (Collection $latest) {
            return $latest->every(fn (Mod $mod) => ! $mod->disabled);
        })
        ->assertViewHas('newest', function (Collection $latest) {
            return $latest->count() === 3;
        })
        ->assertViewHas('updated', function (Collection $updated) {
            return $updated->every(fn (Mod $mod) => ! $mod->disabled);
        })
        ->assertViewHas('updated', function (Collection $updated) {
            return $updated->count() === 3;
        });
});

it('should not display mods with no mod versions', function (): void {
    Mod::factory()->count(3)->create(['featured' => true]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create(['featured' => true]);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $homepage = Livewire::test(Homepage::class)
        ->assertViewHas('featured', function (Collection $featured) {
            return $featured->count() === 1;
        })
        ->assertViewHas('newest', function (Collection $featured) {
            return $featured->count() === 1;
        })
        ->assertViewHas('updated', function (Collection $featured) {
            return $featured->count() === 1;
        });
});

it('should display disabled mods for administrators', function (): void {
    $userRole = UserRole::factory()->administrator()->create();
    $this->actingAs(User::factory()->create(['user_role_id' => $userRole->id]));

    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->create(['featured' => true]);
    Mod::factory()->create(['featured' => true, 'disabled' => true]);
    Mod::factory()->create(['featured' => false]);
    Mod::factory()->create(['featured' => false, 'disabled' => true]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });

    $homepage = Livewire::test(Homepage::class)
        ->assertViewHas('featured', function (Collection $featured) {
            return $featured->count() === 2;
        })
        ->assertViewHas('newest', function (Collection $newest) {
            return $newest->count() === 4;
        })
        ->assertViewHas('updated', function (Collection $updated) {
            return $updated->count() === 4;
        });
});
