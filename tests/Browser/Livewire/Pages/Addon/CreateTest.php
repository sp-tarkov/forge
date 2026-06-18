<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);

    // Seed an SPT version so mods can resolve to publicly visible.
    $this->sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
});

/**
 * Create a publicly visible mod with a published version pinned to the seeded SPT version. Pinning the constraint to
 * `>=3.0.0` keeps the observer from clearing the pivot mid-flow and leaving the mod hidden.
 *
 * @param  array<string, mixed>  $modAttributes
 */
function createVisibleModForAddonCreate(array $modAttributes = [], ?User $owner = null): Mod
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
    it('saves the license value when selected via the listbox', function (): void {
        $owner = User::factory()->withMfa()->create();
        $license = License::factory()->create(['name' => 'MIT License']);
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($owner);

        $page = visit(route('addon.create', ['mod' => $mod->id]));

        $page->assertSee('Addon Information')
            ->assertNoJavascriptErrors()
            ->fill('name', 'Test Addon')
            ->fill('teaser', 'Test teaser text')
            ->fill('textarea[name="description"]', 'Full addon description here')
            ->click('internal:role=combobox[name="License"i]')
            ->waitForText('MIT License')
            ->click('MIT License')
            ->fill('input[placeholder*="github.com/username"]', 'https://github.com/test/test')
            ->click('button[type="submit"]')
            ->waitForText('Addon Created');

        $addon = Addon::query()->where('name', 'Test Addon')->first();
        expect($addon)->not->toBeNull();
        expect($addon->license_id)->toBe($license->id);
    });
});

describe('creation through the UI', function (): void {
    it('allows creating an addon through the UI', function (): void {
        $user = User::factory()->withMfa()->create();
        $license = License::factory()->create();
        $mod = createVisibleModForAddonCreate(owner: $user);

        $this->actingAs($user);

        $page = visit(route('addon.create', ['mod' => $mod->id]));

        $page->waitForText('Addon Information')
            ->assertNoJavascriptErrors()
            ->fill('name', 'Test Addon')
            ->fill('teaser', 'A test addon created via browser test')
            ->fill('textarea[name="description"]', 'This is a comprehensive test of the addon creation flow')
            ->click('Choose license...')
            ->waitForText($license->name)
            ->click($license->name)
            ->fill('input[placeholder="https://github.com/username/addon-name"]', 'https://github.com/test/addon')
            ->click('Create Addon')
            ->assertSee('Test Addon');

        $addon = Addon::query()->where('name', 'Test Addon')->first();
        expect($addon)->not->toBeNull();
        expect($addon->name)->toBe('Test Addon');
    });

    it('shows validation errors when creating addon with invalid data', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = createVisibleModForAddonCreate(owner: $user);

        $this->actingAs($user);

        $page = visit(route('addon.create', ['mod' => $mod->id]));

        $page->waitForText('Addon Information')
            ->click('Create Addon')
            ->waitForText('The name field is required')
            ->assertSee('The teaser field is required')
            ->assertSee('The description field is required')
            ->assertNoJavascriptErrors();
    });
});

describe('addon version creation through the UI', function (): void {
    it('shows validation errors when submitting an empty version form', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = createVisibleModForAddonCreate(owner: $user);
        $addon = Addon::factory()->for($mod)->for($user, 'owner')->create();

        $this->actingAs($user);

        $page = visit(route('addon.version.create', ['addon' => $addon->id]));

        $page->click('Create Version')
            ->assertSee('The version field is required')
            ->assertSee('The description field is required')
            ->assertNoJavascriptErrors();
    });
});
