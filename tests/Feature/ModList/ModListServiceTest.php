<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Services\ModListService;
use App\Support\DataTransferObjects\ResolvedListVersion;
use Illuminate\Support\Facades\DB;

describe('ModListService addMod', function (): void {
    it('adds a mod to a list', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $item = resolve(ModListService::class)->addMod($list, $mod);

        expect($item->listable_type)->toBe(Mod::class);
        expect($item->listable_id)->toBe($mod->id);
        expect($list->fresh()->itemCount())->toBe(1);
    });

    it('is idempotent when adding the same mod twice', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $mod);
        $svc->addMod($list, $mod);

        expect($list->fresh()->itemCount())->toBe(1);
    });

    it('cascades dependency mods alongside the primary mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $dep = Mod::factory()->create();

        resolve(ModListService::class)->addMod($list, $mod, collect([$dep]));

        expect($list->fresh()->itemCount())->toBe(2);
        expect($list->containsMod($dep->id))->toBeTrue();
    });

    it('throws when cascade would exceed the per-list cap', function (): void {
        config()->set('mod-lists.max_items_per_list', 2);
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $mod = Mod::factory()->create();
        $dep1 = Mod::factory()->create();
        $dep2 = Mod::factory()->create();

        expect(fn () => resolve(ModListService::class)->addMod($list, $mod, collect([$dep1, $dep2])))
            ->toThrow(ModListCapacityExceededException::class);

        expect($list->fresh()->itemCount())->toBe(0);
    });
});

describe('ModListService suggestedDependencies', function (): void {
    it('walks the dependency graph recursively across transitive links', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeModWithVersion('A');
        [$modB, $verB] = makeModWithVersion('B');
        [$modC] = makeModWithVersion('C');
        linkModDependency($verA, $modB);
        linkModDependency($verB, $modC);

        $suggested = resolve(ModListService::class)->suggestedDependencies($list, $modA);

        expect($suggested->pluck('id')->all())->toBe([$modB->id, $modC->id]);
    });

    it('omits mods already on the list and stops walking through them', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeModWithVersion('A');
        [$modB, $verB] = makeModWithVersion('B');
        [$modC] = makeModWithVersion('C');
        linkModDependency($verA, $modB);
        linkModDependency($verB, $modC);

        $list->items()->create([
            'listable_type' => Mod::class,
            'listable_id' => $modB->id,
            'position' => 1,
        ]);

        $suggested = resolve(ModListService::class)->suggestedDependencies($list, $modA);

        expect($suggested->pluck('id')->all())->toBe([]);
        unset($modC);
    });

    it('breaks cycles without revisiting mods', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeModWithVersion('A');
        [$modB, $verB] = makeModWithVersion('B');
        linkModDependency($verA, $modB);
        linkModDependency($verB, $modA);

        $suggested = resolve(ModListService::class)->suggestedDependencies($list, $modA);

        expect($suggested->pluck('id')->all())->toBe([$modB->id]);
    });
});

describe('ModListService missingDependenciesForList', function (): void {
    it('returns missing dependencies for every top-level mod on the list', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeModWithVersion('A');
        [$modB, $verB] = makeModWithVersion('B');
        [$modC] = makeModWithVersion('C');
        [$modD] = makeModWithVersion('D');
        linkModDependency($verA, $modB);
        linkModDependency($verB, $modC);

        // modA and modD are both top-level on the list. The graph walk should
        // surface modB and modC (transitively from modA) but not modA/modD.
        resolve(ModListService::class)->addMod($list, $modA);
        resolve(ModListService::class)->addMod($list, $modD);

        $missing = resolve(ModListService::class)->missingDependenciesForList($list->fresh());

        expect($missing->pluck('id')->sort()->values()->all())->toBe(collect([$modB->id, $modC->id])->sort()->values()->all());
    });

    it('returns an empty collection when every dependency is already present', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();

        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        [$modA, $verA] = makeModWithVersion('A');
        [$modB] = makeModWithVersion('B');
        linkModDependency($verA, $modB);

        resolve(ModListService::class)->addMod($list, $modA);
        resolve(ModListService::class)->addMod($list, $modB);

        $missing = resolve(ModListService::class)->missingDependenciesForList($list->fresh());

        expect($missing)->toBeEmpty();
    });

    it('returns an empty collection when the list has no top-level mods', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $missing = resolve(ModListService::class)->missingDependenciesForList($list);

        expect($missing)->toBeEmpty();
    });
});

describe('ModListService addMods', function (): void {
    it('adds many mods in one transaction and returns the added count', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mods = Mod::factory()->count(3)->create();

        $added = resolve(ModListService::class)->addMods($list, $mods);

        expect($added)->toBe(3);
        expect($list->fresh()->itemCount())->toBe(3);
        foreach ($mods as $mod) {
            expect($list->containsMod($mod->id))->toBeTrue();
        }
    });

    it('skips mods already on the list and returns only the count of new additions', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $existing = Mod::factory()->create();
        $newOne = Mod::factory()->create();

        resolve(ModListService::class)->addMod($list, $existing);

        $added = resolve(ModListService::class)->addMods($list, collect([$existing, $newOne]));

        expect($added)->toBe(1);
        expect($list->fresh()->itemCount())->toBe(2);
    });

    it('returns zero and writes nothing when given an empty collection', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $added = resolve(ModListService::class)->addMods($list, collect());

        expect($added)->toBe(0);
        expect($list->fresh()->itemCount())->toBe(0);
    });

    it('throws when the bulk add would exceed the per-list cap', function (): void {
        config()->set('mod-lists.max_items_per_list', 2);
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mods = Mod::factory()->count(3)->create();

        expect(fn () => resolve(ModListService::class)->addMods($list, $mods))
            ->toThrow(ModListCapacityExceededException::class);

        expect($list->fresh()->itemCount())->toBe(0);
    });
});

describe('ModListService addAddon', function (): void {
    it('throws ParentModMissing when parent is not in the list and cascade is not opted in', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        expect(fn () => resolve(ModListService::class)->addAddon($list, $addon))
            ->toThrow(ParentModMissingException::class);
    });

    it('cascades the parent mod when opted in', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        resolve(ModListService::class)->addAddon($list, $addon, includeParentMod: true);

        expect($list->fresh()->itemCount())->toBe(2);
        expect($list->containsMod($mod->id))->toBeTrue();
        expect($list->containsAddon($addon->id))->toBeTrue();
    });
});

describe('ModListService removeItem cascade', function (): void {
    it('removes a mod plus its addons when the mod is removed', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $mod);
        $svc->addAddon($list, $addon);

        $modItem = $list->items()->where('listable_type', Mod::class)->first();
        $svc->removeItem($list, $modItem);

        expect($list->fresh()->itemCount())->toBe(0);
    });

    it('leaves addons for other mods intact when removing one mod', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $modA = Mod::factory()->create();
        $modB = Mod::factory()->create();
        $addonA = Addon::factory()->create(['mod_id' => $modA->id]);
        $addonB = Addon::factory()->create(['mod_id' => $modB->id]);

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $modA);
        $svc->addMod($list, $modB);
        $svc->addAddon($list, $addonA);
        $svc->addAddon($list, $addonB);

        $modAItem = $list->items()->where('listable_type', Mod::class)->where('listable_id', $modA->id)->first();
        $svc->removeItem($list, $modAItem);

        expect($list->fresh()->itemCount())->toBe(2);
        expect($list->containsMod($modB->id))->toBeTrue();
        expect($list->containsAddon($addonB->id))->toBeTrue();
        expect($list->containsAddon($addonA->id))->toBeFalse();
    });
});

describe('ModListService toggleFavourite', function (): void {
    it('adds and removes a mod from Favourites', function (): void {
        $user = User::factory()->create();
        $fav = $user->favouritesList;
        $mod = Mod::factory()->create();

        $svc = resolve(ModListService::class);
        $added = $svc->toggleFavourite($fav, $mod);
        expect($added)->toBeTrue();
        expect($fav->fresh()->containsMod($mod->id))->toBeTrue();

        $removed = $svc->toggleFavourite($fav->fresh(), $mod);
        expect($removed)->toBeFalse();
        expect($fav->fresh()->containsMod($mod->id))->toBeFalse();
    });
});

describe('ModListService cascade from model deletion', function (): void {
    it('removes list items when the referenced mod is deleted', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        resolve(ModListService::class)->addMod($list, $mod);
        $mod->delete();

        expect(ModListItem::query()->count())->toBe(0);
    });

    it('removes list items when the referenced addon is deleted', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $mod);
        $svc->addAddon($list, $addon);

        $addon->delete();

        $addonItems = ModListItem::query()->where('listable_type', Addon::class)->count();
        expect($addonItems)->toBe(0);
    });
});

describe('ModListService ensureFavouritesFor', function (): void {
    it('creates the Favourites list with private visibility', function (): void {
        $user = User::factory()->create();

        $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);

        expect($favourites->is_default)->toBeTrue();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
    });
});

describe('ModListService reorderWithinPositions', function (): void {
    it('rewrites occupied slots in the supplied order', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $modA = Mod::factory()->create();
        $modB = Mod::factory()->create();
        $modC = Mod::factory()->create();

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $modA);
        $svc->addMod($list, $modB);
        $svc->addMod($list, $modC);

        $svc->reorderWithinPositions($list, [$modC->id, $modA->id, $modB->id]);

        $ordered = $list->items()->get()->pluck('listable_id')->all();
        expect($ordered)->toBe([$modC->id, $modA->id, $modB->id]);
    });

    it('leaves positions of items outside the subset untouched', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mods = Mod::factory()->count(4)->create();

        $svc = resolve(ModListService::class);
        foreach ($mods as $mod) {
            $svc->addMod($list, $mod);
        }

        $lastPositionBefore = $list->items()->where('listable_id', $mods[3]->id)->value('position');

        $svc->reorderWithinPositions($list, [$mods[1]->id, $mods[0]->id]);

        expect($list->items()->where('listable_id', $mods[3]->id)->value('position'))->toBe($lastPositionBefore);

        $firstTwo = $list->items()
            ->whereIn('listable_id', [$mods[0]->id, $mods[1]->id])
            ->orderBy('position')
            ->pluck('listable_id')
            ->all();
        expect($firstTwo)->toBe([$mods[1]->id, $mods[0]->id]);
    });

    it('ignores mod ids that are not on the list', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $svc = resolve(ModListService::class);
        $svc->addMod($list, $mod);

        $svc->reorderWithinPositions($list, [999999, $mod->id]);

        expect($list->fresh()->containsMod($mod->id))->toBeTrue();
    });
});

describe('ModListService addAddon capacity', function (): void {
    it('throws when adding an addon plus its parent would exceed the cap', function (): void {
        config()->set('mod-lists.max_items_per_list', 1);
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        expect(fn () => resolve(ModListService::class)->addAddon($list, $addon, includeParentMod: true))
            ->toThrow(ModListCapacityExceededException::class);

        expect($list->fresh()->itemCount())->toBe(0);
    });
});

describe('ModListService toggleFavourite capacity', function (): void {
    it('throws when Favourites is already full', function (): void {
        config()->set('mod-lists.max_items_per_list', 1);
        $user = User::factory()->create();
        $fav = $user->favouritesList;

        $svc = resolve(ModListService::class);
        $svc->toggleFavourite($fav, Mod::factory()->create());

        expect(fn () => $svc->toggleFavourite($fav->fresh(), Mod::factory()->create()))
            ->toThrow(ModListCapacityExceededException::class);
    });
});

describe('ModListService updateNote', function (): void {
    it('updates and clears an item note', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();
        $mod = Mod::factory()->create();

        $svc = resolve(ModListService::class);
        $item = $svc->addMod($list, $mod);

        $svc->updateNote($item, 'Pinned for newcomers');
        expect($item->fresh()->note)->toBe('Pinned for newcomers');

        $svc->updateNote($item, null);
        expect($item->fresh()->note)->toBeNull();
    });
});

describe('ModListService createList', function (): void {
    it('creates a list owned by the user with normalized state', function (): void {
        $user = User::factory()->create();

        $list = resolve(ModListService::class)->createList($user, '  Spaced Title  ', ListVisibility::Private);

        expect($list->owner_id)->toBe($user->id);
        expect($list->title)->toBe('Spaced Title');
        expect($list->visibility)->toBe(ListVisibility::Private);
        expect($list->comments_disabled)->toBeTrue();
        expect($list->is_default)->toBeFalse();
    });
});

describe('ModListService resolveListVersion', function (): void {
    it('returns latestVersion with isIncompatible=false when the list has no target SPT', function (): void {
        $spt = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => null]);

        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $modVersion->sptVersions()->sync([$spt->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved)->toBeInstanceOf(ResolvedListVersion::class);
        expect($resolved->version?->id)->toBe($modVersion->id);
        expect($resolved->isIncompatible)->toBeFalse();
    });

    it('returns the exact-match version, not the latest, when the mod supports the target SPT', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $newerSptVer = ModVersion::factory()->recycle($mod)->create(['version' => '2.0.0']);
        $newerSptVer->sptVersions()->sync([$older->id]);
        $matchingVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.5.0']);
        $matchingVer->sptVersions()->sync([$target->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->version?->id)->toBe($matchingVer->id);
        expect($resolved->isIncompatible)->toBeFalse();
    });

    it('returns the nearest-lower-SPT version with isIncompatible=true when there is no exact match', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $olderVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $olderVer->sptVersions()->sync([$older->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->version?->id)->toBe($olderVer->id);
        expect($resolved->isIncompatible)->toBeTrue();
    });

    it('returns latestVersion with isIncompatible=true when the mod only supports newer SPTs', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $newer = SptVersion::factory()->state(['version' => '4.0.13'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $newerVer = ModVersion::factory()->recycle($mod)->create(['version' => '5.0.0']);
        $newerVer->sptVersions()->sync([$newer->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->version?->id)->toBe($newerVer->id);
        expect($resolved->isIncompatible)->toBeTrue();
    });

    it('pins displaySptVersion to the list target on an exact match even when the version supports newer SPTs', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $newer = SptVersion::factory()->state(['version' => '4.0.13'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $version->sptVersions()->sync([$target->id, $newer->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->version?->id)->toBe($version->id);
        expect($resolved->isIncompatible)->toBeFalse();
        expect($resolved->displaySptVersion?->id)->toBe($target->id);
    });

    it("leaves displaySptVersion null on a closest-fallback match so the card shows the version's own SPT", function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $mod = Mod::factory()->create();
        $olderVer = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $olderVer->sptVersions()->sync([$older->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->displaySptVersion)->toBeNull();
    });

    it('leaves displaySptVersion null when the list has no target SPT', function (): void {
        $spt = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => null]);

        $mod = Mod::factory()->create();
        $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
        $version->sptVersions()->sync([$spt->id]);

        $resolved = resolve(ModListService::class)->resolveListVersion($list, $mod);

        expect($resolved->displaySptVersion)->toBeNull();
    });
});

describe('ModListService resolveListVersions (bulk)', function (): void {
    it('returns the correct entry per mod for a mixed set', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $newer = SptVersion::factory()->state(['version' => '4.0.13'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);

        $compatMod = Mod::factory()->create();
        $compatVer = ModVersion::factory()->recycle($compatMod)->create(['version' => '1.0.0']);
        $compatVer->sptVersions()->sync([$target->id]);

        $olderMod = Mod::factory()->create();
        $olderVer = ModVersion::factory()->recycle($olderMod)->create(['version' => '1.0.0']);
        $olderVer->sptVersions()->sync([$older->id]);

        $newerOnlyMod = Mod::factory()->create();
        $newerOnlyVer = ModVersion::factory()->recycle($newerOnlyMod)->create(['version' => '5.0.0']);
        $newerOnlyVer->sptVersions()->sync([$newer->id]);

        $resolved = resolve(ModListService::class)->resolveListVersions(
            $list,
            collect([$compatMod, $olderMod, $newerOnlyMod]),
        );

        expect($resolved->get($compatMod->id)->version?->id)->toBe($compatVer->id);
        expect($resolved->get($compatMod->id)->isIncompatible)->toBeFalse();
        expect($resolved->get($olderMod->id)->version?->id)->toBe($olderVer->id);
        expect($resolved->get($olderMod->id)->isIncompatible)->toBeTrue();
        expect($resolved->get($newerOnlyMod->id)->version?->id)->toBe($newerOnlyVer->id);
        expect($resolved->get($newerOnlyMod->id)->isIncompatible)->toBeTrue();
    });

    it('issues a bounded number of queries regardless of mod count', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $list = ModList::factory()->public()->create(['spt_version_id' => $target->id]);
        $list->loadMissing('sptVersion');

        $mods = collect();
        for ($i = 0; $i < 8; $i++) {
            $mod = Mod::factory()->create();
            $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
            $version->sptVersions()->sync([($i % 2 === 0 ? $target : $older)->id]);
            $mods->push($mod);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        resolve(ModListService::class)->resolveListVersions($list, $mods);

        // Two queries: one for exact matches, one for closest fallbacks. Any
        // other read (e.g. the target SptVersion) is loaded ahead of time.
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);

        DB::disableQueryLog();
    });

    it('issues no extra queries when the list has no target SPT', function (): void {
        $list = ModList::factory()->public()->create(['spt_version_id' => null]);

        $mods = collect();
        for ($i = 0; $i < 4; $i++) {
            $mod = Mod::factory()->create();
            $spt = SptVersion::factory()->state(['version' => '3.'.$i.'.0'])->create();
            $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
            $version->sptVersions()->sync([$spt->id]);
            $mods->push($mod->load('latestVersion'));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        resolve(ModListService::class)->resolveListVersions($list, $mods);

        expect(DB::getQueryLog())->toBeEmpty();

        DB::disableQueryLog();
    });
});

describe('ModListService listHasIncompatibleMods', function (): void {
    it('returns false when the list has no target SPT version', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create(['spt_version_id' => null]);
        $mod = Mod::factory()->create();
        resolve(ModListService::class)->addMod($list, $mod);

        expect(resolve(ModListService::class)->listHasIncompatibleMods($list))->toBeFalse();
    });

    it('returns false when every mod has a version compatible with the target', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create(['spt_version_id' => $target->id]);

        for ($i = 0; $i < 3; $i++) {
            $mod = Mod::factory()->create();
            $version = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0']);
            $version->sptVersions()->sync([$target->id]);
            resolve(ModListService::class)->addMod($list, $mod);
        }

        expect(resolve(ModListService::class)->listHasIncompatibleMods($list))->toBeFalse();
    });

    it('returns true when at least one mod lacks a compatible version', function (): void {
        $target = SptVersion::factory()->state(['version' => '3.11.4'])->create();
        $older = SptVersion::factory()->state(['version' => '3.10.0'])->create();
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create(['spt_version_id' => $target->id]);

        $compatMod = Mod::factory()->create();
        $compatVer = ModVersion::factory()->recycle($compatMod)->create(['version' => '1.0.0']);
        $compatVer->sptVersions()->sync([$target->id]);

        $incompatMod = Mod::factory()->create();
        $incompatVer = ModVersion::factory()->recycle($incompatMod)->create(['version' => '1.0.0']);
        $incompatVer->sptVersions()->sync([$older->id]);

        resolve(ModListService::class)->addMod($list, $compatMod);
        resolve(ModListService::class)->addMod($list, $incompatMod);

        expect(resolve(ModListService::class)->listHasIncompatibleMods($list))->toBeTrue();
    });
});

/**
 * Build a Mod with a single ModVersion wired to an SPT version so
 * `Mod::latestVersion` (which requires a linked SPT) resolves it.
 * The caller must ensure an SptVersion with version '3.8.0' exists.
 *
 * @return array{0: Mod, 1: ModVersion}
 */
function makeModWithVersion(string $name): array
{
    $mod = Mod::factory()->create(['name' => $name]);
    $version = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    return [$mod, $version];
}

/**
 * Link a ModVersion to another mod via a Dependency row. The DependencyObserver
 * populates `dependencies_resolved` for us when the row is created.
 */
function linkModDependency(ModVersion $from, Mod $to): void
{
    Dependency::factory()->recycle([$from, $to])->create(['constraint' => '*']);
}
