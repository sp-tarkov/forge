<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('badge count calculation', function (): void {
    it('shows zero for users with null timestamp (first visit)', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.11.4',
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(0);
    });

    it('shows correct count of mods created since last view', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Mod created after last view (should be counted)
        $newMod = Mod::factory()->create(['name' => 'New Mod', 'created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($newMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        // Mod created before last view (should not be counted)
        $oldMod = Mod::factory()->create(['name' => 'Old Mod', 'created_at' => now()->subHours(3)]);
        ModVersion::factory()->recycle($oldMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(1);
    });

    it('shows zero when no mods have been created since last view', function (): void {
        $lastViewed = now()->subHour();
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Mod created before last view
        $mod = Mod::factory()->create(['created_at' => now()->subHours(2)]);
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(0);
    });

    it('counts multiple mods correctly', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Create 5 mods created after last view
        for ($i = 0; $i < 5; $i++) {
            $mod = Mod::factory()->create(['created_at' => now()->subHour()]);
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.11.4',
            ]);
        }

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(5);
    });

    it('does not count disabled mods for regular users', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Disabled mod
        $disabledMod = Mod::factory()->disabled()->create(['created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($disabledMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        // Published mod
        $publishedMod = Mod::factory()->create(['created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($publishedMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(1);
    });

    it('counts disabled mods for admins', function (): void {
        $lastViewed = now()->subHours(2);
        $admin = User::factory()->admin()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Disabled mod
        $disabledMod = Mod::factory()->disabled()->create(['created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($disabledMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        // Published mod
        $publishedMod = Mod::factory()->create(['created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($publishedMod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($admin)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(2);
    });
});

describe('badge rendering', function (): void {
    it('does not render badge when count is zero', function (): void {
        $user = User::factory()->create(['mods_created_viewed_at' => null]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(0);
        $component->assertDontSee('bg-red-600');
    });

    it('renders badge when count is greater than zero', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        $mod = Mod::factory()->create(['created_at' => now()->subHour()]);
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.11.4',
        ]);

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(1);
        $component->assertSee('1');
        $component->assertSee('bg-red-600');
    });

    it('shows 99+ when count exceeds 99', function (): void {
        $lastViewed = now()->subHours(2);
        $user = User::factory()->create(['mods_created_viewed_at' => $lastViewed]);
        SptVersion::factory()->create(['version' => '3.11.4']);

        // Create 100 mods
        for ($i = 0; $i < 100; $i++) {
            $mod = Mod::factory()->create(['created_at' => now()->subHour()]);
            ModVersion::factory()->recycle($mod)->create([
                'spt_version_constraint' => '3.11.4',
            ]);
        }

        $component = Livewire::actingAs($user)->test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(100);
        $component->assertSee('99+');
    });
});

describe('unauthenticated users', function (): void {
    it('shows zero count for guests', function (): void {
        $component = Livewire::test('navigation-created-mods-badge');

        expect($component->get('createdCount'))->toBe(0);
    });
});
