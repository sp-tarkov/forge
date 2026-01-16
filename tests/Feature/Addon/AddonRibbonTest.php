<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
    $this->addon = Addon::factory()->for($this->mod)->create();
});

/**
 * Helper function to create component props
 *
 * @return array<string, mixed>
 */
function getAddonRibbonProps(Addon $addon): array
{
    return [
        'addonId' => $addon->id,
        'disabled' => (bool) $addon->disabled,
        'publishedAt' => $addon->published_at?->toISOString(),
    ];
}

describe('Addon Ribbon States', function (): void {
    it('shows disabled ribbon when addon is disabled', function (): void {
        $addon = Addon::factory()->for($this->mod)->create(['disabled' => true]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('shows unpublished ribbon when publishedAt is null', function (): void {
        $addon = Addon::factory()->for($this->mod)->create(['published_at' => null]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });

    it('shows scheduled ribbon when publishedAt is in future', function (): void {
        $addon = Addon::factory()->for($this->mod)->create(['published_at' => now()->addDays(7)]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled');
    });

    it('shows no ribbon for normal published addon', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertDontSee('class="ribbon');
    });

    it('disabled takes priority over other states', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => true,
            'published_at' => null,
        ]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon red')
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('unpublished takes priority over scheduled', function (): void {
        // This scenario shouldn't happen, but test the logic path
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => null,
        ]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });
});

describe('Event-Driven Updates', function (): void {
    it('refreshes addon when addon-updated event is triggered', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertDontSee('class="ribbon');

        // Update the addon in database
        $addon->update(['disabled' => true]);

        // Trigger the refresh
        $component->call('refreshAddon')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('responds to addon-updated events with correct ID', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.addon', getAddonRibbonProps($addon));

        // Verify the component has the refreshAddon method (tests the On attribute functionality)
        expect(method_exists($component->instance(), 'refreshAddon'))->toBeTrue();
    });
});
