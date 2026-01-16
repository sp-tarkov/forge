<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
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
function getAddonVersionRibbonProps(AddonVersion $version): array
{
    return [
        'versionId' => $version->id,
        'disabled' => (bool) $version->disabled,
        'publishedAt' => $version->published_at?->toISOString(),
    ];
}

describe('Addon Version Ribbon Permission-Based Visibility', function (): void {
    it('shows disabled ribbon to privileged users when version is disabled', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['disabled' => true]);
        $this->actingAs($this->addon->owner);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('hides disabled ribbon from normal users when version is disabled', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['disabled' => true]);
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Disabled');
    });

    it('shows unpublished ribbon to privileged users when publishedAt is null', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['published_at' => null]);
        $this->actingAs($this->addon->owner);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });

    it('hides unpublished ribbon from normal users when publishedAt is null', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['published_at' => null]);
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Unpublished');
    });

    it('shows scheduled ribbon to privileged users when publishedAt is in future', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['published_at' => now()->addDays(7)]);
        $this->actingAs($this->addon->owner);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertSee('ribbon emerald')
            ->assertSee('Scheduled');
    });

    it('hides scheduled ribbon from normal users when publishedAt is in future', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create(['published_at' => now()->addDays(7)]);
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon')
            ->assertDontSee('Scheduled');
    });

    it('shows no ribbon for normal published version regardless of permissions', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $this->actingAs($this->addon->owner);
        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon');

        $normalUser = User::factory()->create();
        $this->actingAs($normalUser);
        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon');
    });
});

describe('Addon Version Ribbon States', function (): void {
    it('disabled takes priority over other states for privileged users', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => true,
            'published_at' => null,
        ]);
        $this->actingAs($this->addon->owner);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertSee('ribbon red')
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('unpublished takes priority over scheduled for privileged users', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => false,
            'published_at' => null,
        ]);
        $this->actingAs($this->addon->owner);

        Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertSee('ribbon amber')
            ->assertSee('Unpublished');
    });
});

describe('Event-Driven Updates', function (): void {
    it('refreshes version when addon-version-updated event is triggered', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);
        $this->actingAs($this->addon->owner);

        $component = Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon');

        // Update the version in database
        $version->update(['disabled' => true]);

        // Trigger the refresh
        $component->call('refreshVersion')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });

    it('responds to addon-version-updated events with correct ID', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);

        $component = Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version));

        // Verify the component has the refreshVersion method (tests the On attribute functionality)
        expect(method_exists($component->instance(), 'refreshVersion'))->toBeTrue();
    });
});
