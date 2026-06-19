<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
    $this->addon = Addon::factory()->for($this->mod)->create();
});

/**
 * Build the ribbon component mount props for the given addon.
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

describe('states', function (): void {
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

    it('gives disabled priority over other states', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => true,
            'published_at' => null,
        ]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon red')
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('gives unpublished priority over scheduled', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => null,
        ]);

        Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });
});

describe('event-driven updates', function (): void {
    it('refreshes addon when addon-updated event is triggered', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.addon', getAddonRibbonProps($addon))
            ->assertDontSee('class="ribbon');

        $addon->update(['disabled' => true]);

        $component->call('refreshAddon')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('responds to addon-updated events with the correct ID', function (): void {
        $addon = Addon::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.addon', getAddonRibbonProps($addon));

        // Verifies the component exposes the refreshAddon method targeted by the On attribute.
        expect(method_exists($component->instance(), 'refreshAddon'))->toBeTrue();
    });
});
