<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SourceCodeLink;
use App\Models\SptVersion;
use App\Models\User;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);

    // Seed an SPT version so mods can resolve to publicly visible.
    $this->sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
});

/**
 * Create a publicly visible mod with a published version pinned to the seeded SPT version so the addon edit page can
 * resolve the parent mod as publicly visible.
 *
 * @param  array<string, mixed>  $modAttributes
 */
function createVisibleModForAddonEdit(array $modAttributes = [], ?User $owner = null): Mod
{
    $factory = Mod::factory();
    if ($owner instanceof User) {
        $factory = $factory->for($owner, 'owner');
    }

    $mod = $factory->create($modAttributes);

    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'published_at' => now()->subDay(),
        'spt_version_constraint' => '>=3.0.0',
    ]);
    $modVersion->sptVersions()->sync(test()->sptVersion->id);

    return $mod;
}

describe('license selection', function (): void {
    it('saves the license value when changed via the listbox', function (): void {
        $owner = User::factory()->withMfa()->create();
        License::factory()->create(['name' => 'Original License']);
        $newLicense = License::factory()->create(['name' => 'MIT License']);
        $mod = Mod::factory()->create();

        $originalLicense = License::query()->where('name', 'Original License')->first();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()->for($mod)->for($owner, 'owner')->create([
            'license_id' => $originalLicense->id,
        ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        $page = visit(route('addon.edit', ['addonId' => $addon->id]));

        $page->assertSee('Original License')
            ->assertNoJavascriptErrors()
            ->click('Original License')
            ->waitForText('MIT License')
            ->click('MIT License')
            ->click('Save Changes')
            ->waitForText($addon->name);

        $addon->refresh();
        expect($addon->license_id)->toBe($newLicense->id);
    });
});

describe('editing through the UI', function (): void {
    it('allows editing addon details', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = createVisibleModForAddonEdit(owner: $user);
        $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
            'name' => 'Original Name',
        ]);
        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($user);

        $page = visit(route('addon.edit', ['addonId' => $addon->id]));

        $page->assertSee('Original Name')
            ->assertNoJavascriptErrors()
            ->fill('name', 'Updated Name')
            ->click('Save Changes')
            ->assertSee('Updated Name');

        $addon->refresh();
        expect($addon->name)->toBe('Updated Name');
    });
});
