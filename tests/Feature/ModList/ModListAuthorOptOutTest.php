<?php

declare(strict_types=1);

use App\Exceptions\ModListEntryDisabledException;
use App\Jobs\TombstoneModInListsJob;
use App\Models\Addon;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ModListService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

describe('Mod listsEnabled accessor', function (): void {
    it('reflects the inverse of lists_disabled', function (): void {
        $mod = Mod::factory()->create(['lists_disabled' => false]);
        expect($mod->lists_enabled)->toBeTrue();

        $mod->lists_disabled = true;
        $mod->save();

        expect($mod->fresh()->lists_enabled)->toBeFalse();
    });
});

describe('ModListService addMod opt-out guard', function (): void {
    it('throws when adding an opted-out mod to a user-created list', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => true]);

        expect(fn () => resolve(ModListService::class)->addMod($list, $mod))
            ->toThrow(ModListEntryDisabledException::class);

        expect($list->fresh()->itemCount())->toBe(0);
    });

    it('allows adding an opted-out mod to the favourites list', function (): void {
        $user = User::factory()->create();
        $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
        $mod = Mod::factory()->create(['lists_disabled' => true]);

        resolve(ModListService::class)->addMod($favourites, $mod);

        expect($favourites->fresh()->itemCount())->toBe(1);
        expect($favourites->containsMod($mod->id))->toBeTrue();
    });

    it('filters opted-out mods from explicit dependency cascade', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $primary = Mod::factory()->create(['lists_disabled' => false]);
        $allowedDep = Mod::factory()->create(['lists_disabled' => false]);
        $blockedDep = Mod::factory()->create(['lists_disabled' => true]);

        resolve(ModListService::class)->addMod($list, $primary, collect([$allowedDep, $blockedDep]));

        expect($list->fresh()->itemCount())->toBe(2);
        expect($list->containsMod($primary->id))->toBeTrue();
        expect($list->containsMod($allowedDep->id))->toBeTrue();
        expect($list->containsMod($blockedDep->id))->toBeFalse();
    });
});

describe('ModListService addAddon opt-out inheritance', function (): void {
    it('throws when the parent mod has opted out of lists', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => true]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        expect(fn () => resolve(ModListService::class)->addAddon($list, $addon, includeParentMod: true))
            ->toThrow(ModListEntryDisabledException::class);

        expect($list->fresh()->itemCount())->toBe(0);
    });

    it('throws even when includeParentMod is false', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => true]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        expect(fn () => resolve(ModListService::class)->addAddon($list, $addon))
            ->toThrow(ModListEntryDisabledException::class);
    });
});

describe('ModListService addMods bulk filter', function (): void {
    it('silently skips opted-out mods in bulk adds', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $allowed = Mod::factory()->create(['lists_disabled' => false]);
        $blocked = Mod::factory()->create(['lists_disabled' => true]);

        $added = resolve(ModListService::class)->addMods($list, collect([$allowed, $blocked]));

        expect($added)->toBe(1);
        expect($list->fresh()->itemCount())->toBe(1);
        expect($list->containsMod($allowed->id))->toBeTrue();
        expect($list->containsMod($blocked->id))->toBeFalse();
    });
});

describe('ModListService toggleFavourite bypass', function (): void {
    it('allows toggling an opted-out mod into favourites', function (): void {
        $user = User::factory()->create();
        $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
        $mod = Mod::factory()->create(['lists_disabled' => true]);

        $added = resolve(ModListService::class)->toggleFavourite($favourites, $mod);

        expect($added)->toBeTrue();
        expect($favourites->containsMod($mod->id))->toBeTrue();

        $removed = resolve(ModListService::class)->toggleFavourite($favourites, $mod);
        expect($removed)->toBeFalse();
    });
});

describe('ModListService dependency cascade walk reports skipped', function (): void {
    it('suggestedDependenciesResult splits included from skipped opt-outs', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeListOptOutMod('A');
        [$modB] = makeListOptOutMod('B', listsDisabled: true);
        [$modC] = makeListOptOutMod('C');
        linkListOptOutDep($verA, $modB);
        linkListOptOutDep($verA, $modC);

        $result = resolve(ModListService::class)->suggestedDependenciesResult($list, $modA);

        expect($result->included->pluck('id')->all())->toBe([$modC->id]);
        expect($result->skipped->pluck('id')->all())->toBe([$modB->id]);
    });

    it('missingDependenciesResultForList reports both included and skipped', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeListOptOutMod('A');
        [$modB] = makeListOptOutMod('B', listsDisabled: true);
        [$modC] = makeListOptOutMod('C');
        linkListOptOutDep($verA, $modB);
        linkListOptOutDep($verA, $modC);

        resolve(ModListService::class)->addMod($list, $modA);

        $result = resolve(ModListService::class)->missingDependenciesResultForList($list->fresh());

        expect($result->included->pluck('id')->all())->toBe([$modC->id]);
        expect($result->skipped->pluck('id')->all())->toBe([$modB->id]);
    });

    it('favourites lists do not skip opted-out dependencies', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);

        [$modA, $verA] = makeListOptOutMod('A');
        [$modB] = makeListOptOutMod('B', listsDisabled: true);
        linkListOptOutDep($verA, $modB);

        $result = resolve(ModListService::class)->suggestedDependenciesResult($favourites, $modA);

        expect($result->included->pluck('id')->all())->toBe([$modB->id]);
        expect($result->skipped)->toBeEmpty();
    });
});

describe('ModObserver dispatches the tombstone job', function (): void {
    it('dispatches when lists_disabled flips false to true', function (): void {
        Queue::fake();

        $mod = Mod::factory()->create(['lists_disabled' => false]);
        $mod->lists_disabled = true;
        $mod->save();

        Queue::assertPushed(TombstoneModInListsJob::class, fn (TombstoneModInListsJob $job): bool => $job->modId === $mod->id);
    });

    it('does not dispatch when lists_disabled flips true to false', function (): void {
        Queue::fake();

        $mod = Mod::factory()->create(['lists_disabled' => true]);
        $mod->lists_disabled = false;
        $mod->save();

        Queue::assertNotPushed(TombstoneModInListsJob::class);
    });

    it('does not dispatch on unrelated saves', function (): void {
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        Queue::fake();

        $mod->teaser = 'Updated teaser';
        $mod->save();

        Queue::assertNotPushed(TombstoneModInListsJob::class);
    });
});

describe('TombstoneModInListsJob', function (): void {
    it('marks the mod and its addons as tombstones on non-favourite lists', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['name' => 'The Mod', 'lists_disabled' => false]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'name' => 'The Addon']);

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $mod);
        $svc->addAddon($list, $addon);

        // Flip to opted out, run job
        $mod->lists_disabled = true;
        $mod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        $modItem = ModListItem::query()->where('listable_type', Mod::class)->where('listable_id', $mod->id)->first();
        $addonItem = ModListItem::query()->where('listable_type', Addon::class)->where('listable_id', $addon->id)->first();

        expect($modItem->isTombstone())->toBeTrue();
        expect($modItem->tombstoned_name)->toBe('The Mod');
        expect($addonItem->isTombstone())->toBeTrue();
        expect($addonItem->tombstoned_name)->toBe('The Addon');
    });

    it('leaves favourites entries untouched', function (): void {
        $user = User::factory()->create();
        $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($favourites, $mod);

        $mod->lists_disabled = true;
        $mod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        $favItem = ModListItem::query()
            ->where('mod_list_id', $favourites->id)
            ->where('listable_type', Mod::class)
            ->where('listable_id', $mod->id)
            ->first();

        expect($favItem->isTombstone())->toBeFalse();
        expect($favItem->tombstoned_at)->toBeNull();
    });

    it('bails when the mod has been un-opted-out before the job runs', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($list, $mod);

        // Author flipped the flag back off before the queued job got to run.
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        $modItem = ModListItem::query()->where('listable_id', $mod->id)->first();
        expect($modItem->isTombstone())->toBeFalse();
    });
});

describe('Tombstones are functionally absent', function (): void {
    it('itemCount excludes tombstones for the capacity check', function (): void {
        config()->set('mod-lists.max_items_per_list', 2);

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $tombstonedMod = Mod::factory()->create(['lists_disabled' => false]);
        $anotherMod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($list, $tombstonedMod);

        // Tombstone the first mod
        $tombstonedMod->lists_disabled = true;
        $tombstonedMod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($tombstonedMod->id));

        // The tombstone must not consume a capacity slot.
        expect($list->fresh()->itemCount())->toBe(0);

        // Adding two more active mods should now succeed under cap=2.
        resolve(ModListService::class)->addMod($list, $anotherMod);
        $thirdMod = Mod::factory()->create(['lists_disabled' => false]);
        resolve(ModListService::class)->addMod($list, $thirdMod);

        expect($list->fresh()->itemCount())->toBe(2);
    });

    it('containsMod returns false for a tombstoned mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($list, $mod);

        $mod->lists_disabled = true;
        $mod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        expect($list->fresh()->containsMod($mod->id))->toBeFalse();
    });

    it('forkList omits tombstones from the new list', function (): void {
        $owner = User::factory()->create();
        $forker = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();
        $modActive = Mod::factory()->create(['lists_disabled' => false]);
        $modToTombstone = Mod::factory()->create(['lists_disabled' => false]);

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $modActive);
        $svc->addMod($list, $modToTombstone);

        // Tombstone one
        $modToTombstone->lists_disabled = true;
        $modToTombstone->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($modToTombstone->id));

        $fork = $svc->forkList($forker, $list->fresh(), 'My Fork');

        expect($fork->itemCount())->toBe(1);
        expect($fork->containsMod($modActive->id))->toBeTrue();
        expect($fork->containsMod($modToTombstone->id))->toBeFalse();
    });

    it('curator can remove a tombstone via the normal remove flow', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($list, $mod);

        $mod->lists_disabled = true;
        $mod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        $tombstone = ModListItem::query()
            ->where('mod_list_id', $list->id)
            ->where('listable_id', $mod->id)
            ->first();

        resolve(ModListService::class)->removeItem($list, $tombstone);

        expect(ModListItem::query()->where('mod_list_id', $list->id)->count())->toBe(0);
    });

    it('re-enabling lists_disabled does not revive tombstones', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create(['lists_disabled' => false]);

        resolve(ModListService::class)->addMod($list, $mod);

        $mod->lists_disabled = true;
        $mod->saveQuietly();
        dispatch_sync(new TombstoneModInListsJob($mod->id));

        // Author re-enables; tombstones should NOT be auto-restored.
        $mod->lists_disabled = false;
        $mod->save();

        $item = ModListItem::query()->where('listable_id', $mod->id)->first();
        expect($item->isTombstone())->toBeTrue();
    });
});

describe('Tombstone name visibility on the list show page', function (): void {
    it('hides the captured mod name from guests', function (): void {
        [$list, $mod] = seedTombstoneOnList();

        $this->get(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]))
            ->assertOk()
            ->assertDontSee($mod->name)
            ->assertSee('Removed item');
    });

    it('hides the captured mod name from a regular signed-in user', function (): void {
        [$list, $mod] = seedTombstoneOnList();
        $regular = User::factory()->create();

        Livewire::actingAs($regular)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertDontSee($mod->name)
            ->assertSee('Removed item');
    });

    it('hides the captured mod name from the list owner if they are neither the mod author nor staff', function (): void {
        [$list, $mod, $owner] = seedTombstoneOnList();

        Livewire::actingAs($owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertDontSee($mod->name)
            ->assertSee('Removed item');
    });

    it('shows the captured mod name to the mod owner', function (): void {
        [$list, $mod] = seedTombstoneOnList();

        Livewire::actingAs($mod->owner)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertSee($mod->name);
    });

    it('shows the captured mod name to additional authors of the mod', function (): void {
        [$list, $mod] = seedTombstoneOnList();
        $coAuthor = User::factory()->create();
        $mod->additionalAuthors()->attach($coAuthor);

        Livewire::actingAs($coAuthor)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertSee($mod->name);
    });

    it('shows the captured mod name to staff and moderators', function (): void {
        [$list, $mod] = seedTombstoneOnList();

        $staffRole = UserRole::factory()->staff()->create();
        $staff = User::factory()->for($staffRole, 'role')->create();

        Livewire::actingAs($staff)
            ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
            ->assertSee($mod->name);
    });
});

/**
 * Build a public mod list that contains one tombstoned mod, owned by a fresh regular user who is neither the mod's
 * author nor staff. Returns the list, the underlying mod, and the list owner.
 *
 * @return array{0: ModList, 1: Mod, 2: User}
 */
function seedTombstoneOnList(): array
{
    $listOwner = User::factory()->create();
    $modOwner = User::factory()->create();
    $list = ModList::factory()->for($listOwner, 'owner')->public()->create();
    $mod = Mod::factory()->for($modOwner, 'owner')->create(['lists_disabled' => false]);

    resolve(ModListService::class)->addMod($list, $mod);

    $mod->lists_disabled = true;
    $mod->saveQuietly();
    dispatch_sync(new TombstoneModInListsJob($mod->id));

    return [$list->fresh(), $mod->fresh(), $listOwner];
}

/**
 * @return array{0: Mod, 1: ModVersion}
 */
function makeListOptOutMod(string $name, bool $listsDisabled = false): array
{
    $mod = Mod::factory()->create(['name' => $name, 'lists_disabled' => $listsDisabled]);
    $version = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    return [$mod, $version];
}

function linkListOptOutDep(ModVersion $from, Mod $to): void
{
    Dependency::factory()->recycle([$from, $to])->create(['constraint' => '*']);
}
