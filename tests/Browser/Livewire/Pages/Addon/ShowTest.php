<?php

declare(strict_types=1);

use App\Models\Addon;
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
 * Create a publicly visible mod with a published version pinned to the seeded SPT version so the addon show page
 * resolves the parent mod as publicly visible.
 *
 * @param  array<string, mixed>  $modAttributes
 */
function createVisibleModForAddonShowBrowser(array $modAttributes = [], ?User $owner = null): Mod
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

describe('global search', function (): void {
    it('finds addons in global search', function (): void {
        $mod = createVisibleModForAddonShowBrowser(['name' => 'Parent Mod']);
        Addon::factory()->for($mod)->published()->withVersions(1)->create([
            'name' => 'Unique Search Test Addon',
        ]);

        $page = visit('/');

        $page->click('Search...')
            ->type('#global-search', 'Unique Search Test')
            ->waitForText('Unique Search Test Addon')
            ->assertSee('ADDON')
            ->assertSee('Unique Search Test Addon');
    });

    it('shows detached status in search results', function (): void {
        $mod = createVisibleModForAddonShowBrowser(['name' => 'Parent Mod']);
        Addon::factory()->for($mod)->published()->withVersions(1)->detached()->create([
            'name' => 'Detached Search Addon',
        ]);

        $page = visit('/');

        $page->click('Search...')
            ->type('#global-search', 'Detached Search')
            ->waitForText('Detached Search Addon')
            ->assertSee('ADDON')
            ->assertSee('Detached');
    });
});

describe('responsive design', function (): void {
    it('displays addon cards correctly on mobile', function (): void {
        $mod = createVisibleModForAddonShowBrowser();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

        $page = visit(route('addon.show', [$addon->id, $addon->slug]))
            ->resize(375, 667);

        $page->assertSee($addon->name)
            ->assertSee('ADDON')
            ->assertNoJavascriptErrors();
    });

    it('displays addon details correctly on tablet', function (): void {
        $mod = createVisibleModForAddonShowBrowser();
        $addon = Addon::factory()->for($mod)->published()->withVersions(1)->create();

        $page = visit(route('addon.show', [$addon->id, $addon->slug]))
            ->resize(768, 1024);

        $page->assertSee($addon->name)
            ->assertNoJavascriptErrors();
    });
});
