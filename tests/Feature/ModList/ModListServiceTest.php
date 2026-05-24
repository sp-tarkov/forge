<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\User;
use App\Services\ModListService;

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
