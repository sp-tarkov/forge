<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

/**
 * Helper function to create component props.
 *
 * @return array<string, mixed>
 */
function getModRibbonProps(Mod $mod, bool $homepageFeatured = false): array
{
    return [
        'modId' => $mod->id,
        'disabled' => (bool) $mod->disabled,
        'publishedAt' => $mod->published_at?->toISOString(),
        'featured' => (bool) $mod->featured,
        'homepageFeatured' => $homepageFeatured,
    ];
}

describe('Mod Ribbon States', function (): void {
    it('shows disabled ribbon when mod is disabled', function (): void {
        $mod = Mod::factory()->create(['disabled' => true]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('shows unpublished ribbon when publishedAt is null', function (): void {
        $mod = Mod::factory()->create(['published_at' => null]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });

    it('shows scheduled ribbon when publishedAt is in future', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()->addDays(7)]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled');
    });

    it('shows featured ribbon when featured and not on homepage', function (): void {
        $mod = Mod::factory()->create([
            'featured' => true,
            'published_at' => now()->subDays(1),
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod, false))
            ->assertSee('ribbon sky')
            ->assertSee('Featured!');
    });

    it('hides featured ribbon when on homepage', function (): void {
        $mod = Mod::factory()->create([
            'featured' => true,
            'published_at' => now()->subDays(1),
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod, true))
            ->assertDontSee('class="ribbon');
    });

    it('shows no ribbon for normal published mod', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'featured' => false,
            'published_at' => now()->subDays(1),
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertDontSee('class="ribbon');
    });

    it('disabled takes priority over other states', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => true,
            'published_at' => null,
            'featured' => true,
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon red')
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished')
            ->assertDontSee('Featured');
    });

    it('unpublished takes priority over featured', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => null,
            'featured' => true,
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished')
            ->assertDontSee('Featured');
    });

    it('scheduled takes priority over featured', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->addDays(7),
            'featured' => true,
        ]);

        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled')
            ->assertDontSee('Featured');
    });
});

describe('Lazy Visibility Resolution', function (): void {
    it('runs no mod version queries for guests', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()->subDay()]);

        DB::enableQueryLog();
        Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertDontSee('class="ribbon');
        $versionQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'mod_versions'));
        DB::disableQueryLog();

        expect($versionQueries)->toBeEmpty();
    });

    it('shows the unpublished warning to the owner when the mod has no visible versions', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => now()->subDay()]);

        Livewire::actingAs($owner)
            ->test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });

    it('shows no warning to the owner when the mod has a visible legacy version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => now()->subDay()]);
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        Livewire::actingAs($owner)
            ->test('ribbon.mod', getModRibbonProps($mod))
            ->assertDontSee('class="ribbon');
    });

    it('uses a passed publicly-visible value without querying mod versions', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['published_at' => now()->subDay()]);

        DB::enableQueryLog();
        Livewire::actingAs($admin)
            ->test('ribbon.mod', [...getModRibbonProps($mod), 'publiclyVisible' => true])
            ->assertDontSee('class="ribbon');
        $versionQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'mod_versions'));
        DB::disableQueryLog();

        expect($versionQueries)->toBeEmpty();
    });

    it('resolves visibility for an admin when no publicly-visible value is passed', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create(['published_at' => now()->subDay()]);

        Livewire::actingAs($admin)
            ->test('ribbon.mod', getModRibbonProps($mod))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });
});

describe('Event-Driven Updates', function (): void {
    it('refreshes mod when mod-updated event is triggered', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'featured' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.mod', getModRibbonProps($mod))
            ->assertDontSee('class="ribbon');

        // Update the mod in database
        $mod->update(['disabled' => true]);

        // Trigger the refresh
        $component->call('refreshMod')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });
});
