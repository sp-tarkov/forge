<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ModListService
{
    /**
     * Add a mod to the list, optionally cascading its dependencies.
     *
     * @param  Collection<int, Mod>|array<int, Mod>  $dependenciesToAdd
     *
     * @throws ModListCapacityExceededException
     */
    public function addMod(
        ModList $modList,
        Mod $mod,
        ?string $note = null,
        Collection|array $dependenciesToAdd = [],
    ): ModListItem {
        $deps = $dependenciesToAdd instanceof Collection ? $dependenciesToAdd : collect($dependenciesToAdd);
        $deps = $deps
            ->reject(fn (Mod $candidate): bool => $candidate->id === $mod->id)
            ->reject(fn (Mod $candidate): bool => $modList->containsMod($candidate->id))
            ->values();

        $projectedCount = $modList->itemCount();
        $projectedCount += $modList->containsMod($mod->id) ? 0 : 1;
        $projectedCount += $deps->count();

        $this->assertWithinCapacity($modList, $projectedCount);

        return DB::transaction(function () use ($modList, $mod, $note, $deps): ModListItem {
            $primary = $this->createItem($modList, Mod::class, $mod->id, $note, false);

            foreach ($deps as $dep) {
                $this->createItem($modList, Mod::class, $dep->id, null, true);
            }

            return $primary;
        });
    }

    /**
     * Add an addon to the list.
     *
     * If the parent mod isn't already in the list the caller must opt in via
     * `$includeParentMod`; otherwise a ParentModMissingException is thrown so the
     * UI can prompt for confirmation.
     *
     * @throws ParentModMissingException
     * @throws ModListCapacityExceededException
     */
    public function addAddon(
        ModList $modList,
        Addon $addon,
        ?string $note = null,
        bool $includeParentMod = false,
    ): ModListItem {
        $needsParent = $addon->mod_id !== null && ! $modList->containsMod($addon->mod_id);

        throw_if($needsParent && ! $includeParentMod, ParentModMissingException::class, $modList, $addon);

        $projectedCount = $modList->itemCount();
        $projectedCount += $modList->containsAddon($addon->id) ? 0 : 1;
        $projectedCount += $needsParent ? 1 : 0;

        $this->assertWithinCapacity($modList, $projectedCount);

        return DB::transaction(function () use ($modList, $addon, $note, $needsParent): ModListItem {
            if ($needsParent && $addon->mod_id !== null) {
                $this->createItem($modList, Mod::class, $addon->mod_id, null, false);
            }

            return $this->createItem($modList, Addon::class, $addon->id, $note, false);
        });
    }

    /**
     * Toggle a mod in the user's Favourites list without the dependency prompt.
     * Returns true if the mod was added, false if it was removed.
     */
    public function toggleFavourite(ModList $favourites, Mod $mod): bool
    {
        if ($favourites->containsMod($mod->id)) {
            ModListItem::query()
                ->where('mod_list_id', $favourites->id)
                ->where('listable_type', Mod::class)
                ->where('listable_id', $mod->id)
                ->delete();

            return false;
        }

        $this->assertWithinCapacity($favourites, $favourites->itemCount() + 1);
        $this->createItem($favourites, Mod::class, $mod->id, null, false);

        return true;
    }

    /**
     * Remove an item from a list. Cascades addons when a parent mod is removed.
     */
    public function removeItem(ModList $modList, ModListItem $item): void
    {
        DB::transaction(function () use ($modList, $item): void {
            if ($item->listable_type === Mod::class) {
                ModListItem::query()
                    ->where('mod_list_id', $modList->id)
                    ->where('listable_type', Addon::class)
                    ->whereIn('listable_id', Addon::query()->where('mod_id', $item->listable_id)->pluck('id'))
                    ->delete();
            }

            $item->delete();
        });
    }

    /**
     * Reorder top-level mod items within a list. Accepts an array of mod ids.
     *
     * @param  array<int, int>  $orderedModIds
     */
    public function reorder(ModList $modList, array $orderedModIds): void
    {
        DB::transaction(function () use ($modList, $orderedModIds): void {
            foreach ($orderedModIds as $index => $modId) {
                ModListItem::query()
                    ->where('mod_list_id', $modList->id)
                    ->where('listable_type', Mod::class)
                    ->where('listable_id', $modId)
                    ->update(['position' => $index]);
            }
        });
    }

    /**
     * Resolve the suggested dependency mods to cascade when adding a mod to the list.
     *
     * @return Collection<int, Mod>
     */
    public function suggestedDependencies(ModList $modList, Mod $mod): Collection
    {
        $modVersion = $this->resolveModVersion($modList, $mod);

        if (! $modVersion instanceof ModVersion) {
            return new Collection;
        }

        $deps = $modVersion->latestDependenciesResolved()
            ->with('mod:id,name,slug,thumbnail,thumbnail_hash,owner_id')
            ->get();

        /** @var Collection<int, Mod> $mods */
        $mods = new Collection;
        $seen = [];
        foreach ($deps as $depVersion) {
            $depMod = $depVersion->mod;
            if ($depMod->id === $mod->id) {
                continue;
            }

            if ($modList->containsMod($depMod->id)) {
                continue;
            }

            if (isset($seen[$depMod->id])) {
                continue;
            }

            $seen[$depMod->id] = true;
            $mods->push($depMod);
        }

        return $mods;
    }

    /**
     * Pick the ModVersion a list would show for a given mod:
     *  - The list's SPT-compatible version if the list has an spt_version_id
     *  - Otherwise the mod's latest published version
     */
    private function resolveModVersion(ModList $modList, Mod $mod): ?ModVersion
    {
        if ($modList->spt_version_id !== null) {
            return $mod->versions()
                ->whereHas('sptVersions', fn (Builder $q): Builder => $q->where('spt_versions.id', $modList->spt_version_id))
                ->first();
        }

        $mod->loadMissing('latestVersion');

        return $mod->latestVersion;
    }

    /**
     * @throws ModListCapacityExceededException
     */
    private function assertWithinCapacity(ModList $modList, int $projectedCount): void
    {
        $max = config()->integer('mod-lists.max_items_per_list', 250);

        throw_if($projectedCount > $max, ModListCapacityExceededException::class, $modList, $projectedCount, $max);
    }

    /**
     * Internal helper to create (or no-op on duplicate) a list item.
     */
    private function createItem(
        ModList $modList,
        string $listableType,
        int $listableId,
        ?string $note,
        bool $addedAsDependency,
    ): ModListItem {
        /** @var ModListItem $item */
        $item = ModListItem::query()->firstOrCreate(
            [
                'mod_list_id' => $modList->id,
                'listable_type' => $listableType,
                'listable_id' => $listableId,
            ],
            [
                'note' => $note,
                'position' => $this->nextPosition($modList),
                'added_as_dependency' => $addedAsDependency,
            ],
        );

        return $item;
    }

    private function nextPosition(ModList $modList): int
    {
        /** @var int|null $max */
        $max = ModListItem::query()
            ->where('mod_list_id', $modList->id)
            ->max('position');

        return ($max ?? 0) + 1;
    }
}
