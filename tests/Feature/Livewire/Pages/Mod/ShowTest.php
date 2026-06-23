<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutDefer();
});

/**
 * Create a publicly visible mod with a published version pinned to a known SPT version so the mod always resolves to
 * publicly viewable. Without pinning the constraint the factory's random value can leave the mod hidden.
 *
 * @param  array<string, mixed>  $attributes
 */
function createVisibleModForShow(array $attributes = []): Mod
{
    $sptVersion = SptVersion::query()->firstOrCreate(
        ['version' => '3.9.0'],
        SptVersion::factory()->make(['version' => '3.9.0'])->toArray(),
    );

    $mod = Mod::factory()->create($attributes);

    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'published_at' => now()->subDay(),
        'spt_version_constraint' => '>=3.0.0',
    ]);
    $modVersion->sptVersions()->sync($sptVersion->id);

    return $mod;
}

/**
 * Add the given mod to a list as an active (or tombstoned) item.
 *
 * @param  array<string, mixed>  $attributes
 */
function addModToList(Mod $mod, ModList $list, array $attributes = []): ModListItem
{
    return ModListItem::factory()->create([
        'mod_list_id' => $list->id,
        'listable_type' => Mod::class,
        'listable_id' => $mod->id,
        ...$attributes,
    ]);
}

function renderShow(Mod $mod): Testable
{
    return Livewire::test('pages::mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]);
}

describe('list presence summary', function (): void {
    it('omits the container when the mod is in no lists or favourites', function (): void {
        $mod = createVisibleModForShow();

        renderShow($mod)
            ->assertSuccessful()
            ->assertDontSee('This mod is featured in')
            ->assertDontSee('This mod has been favourited');
    });

    it('counts curated lists of every visibility, excluding favourites', function (): void {
        $mod = createVisibleModForShow();

        addModToList($mod, ModList::factory()->public()->create());
        addModToList($mod, ModList::factory()->hidden()->create());
        addModToList($mod, ModList::factory()->private()->create());

        renderShow($mod)
            ->assertSuccessful()
            ->assertSee('This mod is featured in 3 lists.');
    });

    it('excludes moderator-disabled lists from the count', function (): void {
        $mod = createVisibleModForShow();

        addModToList($mod, ModList::factory()->public()->create());
        addModToList($mod, ModList::factory()->public()->disabled()->create());

        renderShow($mod)
            ->assertSuccessful()
            ->assertSee('This mod is featured in 1 list.');
    });

    it('excludes tombstoned list items from the count', function (): void {
        $mod = createVisibleModForShow();

        addModToList($mod, ModList::factory()->public()->create());
        addModToList($mod, ModList::factory()->public()->create(), ['tombstoned_at' => now()]);

        renderShow($mod)
            ->assertSuccessful()
            ->assertSee('This mod is featured in 1 list.');
    });

    it('counts favourites separately from curated lists', function (): void {
        $mod = createVisibleModForShow();

        addModToList($mod, ModList::factory()->favourites()->create());
        addModToList($mod, ModList::factory()->favourites()->create());
        addModToList($mod, ModList::factory()->favourites()->create());

        renderShow($mod)
            ->assertSuccessful()
            ->assertSee('This mod has been favourited 3 times.');
    });

    it('combines and singularizes both clauses', function (): void {
        $mod = createVisibleModForShow();

        addModToList($mod, ModList::factory()->public()->create());
        addModToList($mod, ModList::factory()->favourites()->create());

        renderShow($mod)
            ->assertSuccessful()
            ->assertSee('This mod is featured in 1 list and favourited 1 time.');
    });
});
