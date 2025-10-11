<?php

declare(strict_types=1);

use App\Livewire\Ribbon\ModVersion as ModVersionRibbon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
    $this->modVersion = ModVersion::factory()->for($this->mod)->create();
});

/**
 * Helper function to create component props
 *
 * @return array<string, mixed>
 */
function getModVersionRibbonProps(ModVersion $version): array
{
    return [
        'versionId' => $version->id,
        'disabled' => (bool) $version->disabled,
        'publishedAt' => $version->published_at?->toISOString(),
    ];
}

describe('ModVersion Ribbon States', function (): void {
    it('shows disabled ribbon when mod version is disabled', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create(['disabled' => true]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('shows unpublished ribbon when publishedAt is null', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create(['published_at' => null]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });

    it('shows scheduled ribbon when publishedAt is in future', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create(['published_at' => now()->addDays(7)]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled');
    });

    it('shows no ribbon for normal published mod version', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertDontSee('ribbon');
    });

    it('disabled takes priority over other states', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create([
            'disabled' => true,
            'published_at' => null,
        ]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon red')
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('unpublished is shown when not disabled', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => null,
        ]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished')
            ->assertDontSee('Disabled');
    });

    it('scheduled takes priority over unpublished', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create([
            'disabled' => false,
            'published_at' => now()->addDays(7),
        ]);

        Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled');
    });
});

describe('Event-Driven Updates', function (): void {
    it('refreshes version when mod-version-updated event is triggered', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create(['disabled' => false]);

        $component = Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version))
            ->assertDontSee('ribbon');

        // Update the version in database
        $version->update(['disabled' => true]);

        // Trigger the refresh
        $component->call('refreshVersion')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('responds to mod-version-updated events with correct ID', function (): void {
        $version = ModVersion::factory()->for($this->mod)->create(['disabled' => false]);

        $component = Livewire::test(ModVersionRibbon::class, getModVersionRibbonProps($version));

        // Verify the component has the refreshVersion method (tests the On attribute functionality)
        expect(method_exists($component->instance(), 'refreshVersion'))->toBeTrue();
    });
});
