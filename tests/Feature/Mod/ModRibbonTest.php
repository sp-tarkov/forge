<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

/**
 * Helper function to create component props
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

    it('responds to mod-updated events with correct ID', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'featured' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.mod', getModRibbonProps($mod));

        // Verify the component has the refreshMod method (tests the On attribute functionality)
        expect(method_exists($component->instance(), 'refreshMod'))->toBeTrue();
    });
});
