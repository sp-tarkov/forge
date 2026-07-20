<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
    $this->addon = Addon::factory()->for($this->mod)->create();
});

/**
 * Build the ribbon component mount props for the given addon version.
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

describe('permission-based visibility', function (): void {
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

describe('states', function (): void {
    it('gives disabled priority over other states for privileged users', function (): void {
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

    it('gives unpublished priority over scheduled for privileged users', function (): void {
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

describe('event-driven updates', function (): void {
    it('refreshes version when addon-version-updated event is triggered', function (): void {
        $version = AddonVersion::factory()->for($this->addon)->create([
            'disabled' => false,
            'published_at' => now()->subDays(1),
        ]);
        $this->actingAs($this->addon->owner);

        $component = Livewire::test('ribbon.addon-version', getAddonVersionRibbonProps($version))
            ->assertDontSee('class="ribbon');

        $version->update(['disabled' => true]);

        $component->call('refreshVersion')
            ->assertSee('ribbon red')
            ->assertSee('Disabled');
    });
});
