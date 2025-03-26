<?php

declare(strict_types=1);

use App\Livewire\Mod\Homepage;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $featured = Livewire::test(Homepage::class)->get('featured');

    expect($featured)->toHaveCount(3)
        ->and($featured)->pluck('featured')->each->toBeTrue();
});

it('should not display disabled mods', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->count(3)->create(['featured' => true]);
    Mod::factory()->count(3)->disabled()->create(['featured' => false]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });

    $livewire = Livewire::test(Homepage::class);

    expect($livewire->get('featured'))->toHaveCount(3)
        ->and($livewire->get('featured'))->pluck('disabled')->each->toBeFalse()
        ->and($livewire->get('latest'))->toHaveCount(3)
        ->and($livewire->get('latest'))->pluck('disabled')->each->toBeFalse()
        ->and($livewire->get('updated'))->toHaveCount(3)
        ->and($livewire->get('updated'))->pluck('disabled')->each->toBeFalse();
});

it('should not display mods with no mod versions', function (): void {
    Mod::factory()->count(3)->create(['featured' => true]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create(['featured' => true]);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $livewire = Livewire::test(Homepage::class);

    expect($livewire->get('featured'))->toHaveCount(1)
        ->and($livewire->get('latest'))->toHaveCount(1)
        ->and($livewire->get('updated'))->toHaveCount(1);
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

    $livewire = Livewire::test(Homepage::class);

    expect($livewire->get('featured'))->toHaveCount(2)
        ->and(collect($livewire->get('featured'))->filter(fn ($mod) => $mod->disabled))->toHaveCount(1)
        ->and($livewire->get('latest'))->toHaveCount(4)
        ->and(collect($livewire->get('latest'))->filter(fn ($mod) => $mod->disabled))->toHaveCount(2)
        ->and($livewire->get('updated'))->toHaveCount(4)
        ->and(collect($livewire->get('updated'))->filter(fn ($mod) => $mod->disabled))->toHaveCount(2);
});
