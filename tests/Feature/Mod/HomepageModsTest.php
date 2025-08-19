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

describe('homepage featured mods', function (): void {
    it('should only display featured mods in the featured section', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        Mod::factory()->count(3)->create(['featured' => true]);
        Mod::factory()->count(3)->create(['featured' => false]);
        Mod::all()->each(function ($mod): void {
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        });

        // Assert that the featured mods are the ones that are actually featured
        $featured = Livewire::test(Homepage::class)
            ->assertViewHas('featured', fn ($featured) => $featured->every(fn ($mod) => $mod->featured));
    });
});

describe('homepage mod visibility', function (): void {
    it('should not display disabled mods', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        Mod::factory()->count(3)->create(['featured' => true]);
        Mod::factory()->count(3)->disabled()->create(['featured' => false]);
        Mod::all()->each(function ($mod): void {
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        });

        $homepage = Livewire::test(Homepage::class)
            ->assertViewHas('featured', fn (Collection $featured) => $featured->every(fn (Mod $mod) => $mod->featured))
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 3)
            ->assertViewHas('newest', fn (Collection $latest) => $latest->every(fn (Mod $mod): bool => ! $mod->disabled))
            ->assertViewHas('newest', fn (Collection $latest): bool => $latest->count() === 3)
            ->assertViewHas('updated', fn (Collection $updated) => $updated->every(fn (Mod $mod): bool => ! $mod->disabled))
            ->assertViewHas('updated', fn (Collection $updated): bool => $updated->count() === 3);
    });

    it('should not display mods with no mod versions', function (): void {
        Mod::factory()->count(3)->create(['featured' => true]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['featured' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $homepage = Livewire::test(Homepage::class)
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 1)
            ->assertViewHas('newest', fn (Collection $featured): bool => $featured->count() === 1)
            ->assertViewHas('updated', fn (Collection $featured): bool => $featured->count() === 1);
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
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 2)
            ->assertViewHas('newest', fn (Collection $newest): bool => $newest->count() === 4)
            ->assertViewHas('updated', fn (Collection $updated): bool => $updated->count() === 4);
    });
});
