<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\View\Components\HomepageMods;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $component = new HomepageMods;
    $renderedData = $component->render();

    expect($renderedData['featured']['mods'])->toHaveCount(3)
        ->and($renderedData['featured']['mods'])->pluck('featured')->each->toBeTrue();
});

it('should not display disabled mods', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    Mod::factory()->count(3)->create(['featured' => true]);
    Mod::factory()->count(3)->disabled()->create(['featured' => false]);
    Mod::all()->each(function ($mod): void {
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
    });

    $component = new HomepageMods;
    $renderedData = $component->render();

    expect($renderedData['featured']['mods'])->toHaveCount(3)
        ->and($renderedData['featured']['mods'])->pluck('disabled')->each->toBeFalse()
        ->and($renderedData['latest']['mods'])->toHaveCount(3)
        ->and($renderedData['latest']['mods'])->pluck('disabled')->each->toBeFalse()
        ->and($renderedData['updated']['mods'])->toHaveCount(3)
        ->and($renderedData['updated']['mods'])->pluck('disabled')->each->toBeFalse();
});

it('should not display mods with no mod versions', function (): void {
    Mod::factory()->count(3)->create(['featured' => true]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create(['featured' => true]);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $component = new HomepageMods;
    $renderedData = $component->render();

    expect($renderedData['featured']['mods'])->toHaveCount(1)
        ->and($renderedData['latest']['mods'])->toHaveCount(1)
        ->and($renderedData['updated']['mods'])->toHaveCount(1);
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

    $component = new HomepageMods;
    $renderedData = $component->render();

    expect($renderedData['featured']['mods'])->toHaveCount(2)
        ->and(collect($renderedData['featured']['mods'])->filter(fn ($mod) => $mod->disabled))->toHaveCount(1)
        ->and($renderedData['latest']['mods'])->toHaveCount(4)
        ->and(collect($renderedData['latest']['mods'])->filter(fn ($mod) => $mod->disabled))->toHaveCount(2)
        ->and($renderedData['updated']['mods'])->toHaveCount(4)
        ->and(collect($renderedData['updated']['mods'])->filter(fn ($mod) => $mod->disabled))->toHaveCount(2);
});
