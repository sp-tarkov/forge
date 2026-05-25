<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Support\DataTransferObjects\ResolvedListVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ModListService
{
    /**
     * Create (or return the existing) immutable default Favourites list for a user.
     *
     * The slug is left for ModListObserver to derive and de-duplicate, so there
     * is no check-then-insert race against the (owner_id, slug) unique index.
     */
    public function ensureFavouritesFor(User $user): ModList
    {
        $title = config()->string('mod-lists.favourites.title', 'Favourites');

        /** @var ModList $modList */
        $modList = ModList::query()->firstOrCreate(
            [
                'owner_id' => $user->id,
                'is_default' => true,
            ],
            [
                'title' => $title,
                'visibility' => ListVisibility::Private,
                'comments_disabled' => false,
            ],
        );

        return $modList;
    }

    /**
     * Create a new (non-default) curated list for a user.
     *
     * Centralizes list creation so inline and full-form callers persist a list
     * with the same normalized shape.
     */
    public function createList(User $owner, string $title, ListVisibility $visibility): ModList
    {
        $list = new ModList;
        $list->owner_id = $owner->id;
        $list->title = mb_trim($title);
        $list->visibility = $visibility;
        $list->is_default = false;
        // Private lists never surface a comment thread.
        $list->comments_disabled = $visibility === ListVisibility::Private;
        $list->save();

        return $list;
    }

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
        Collection|array $dependenciesToAdd = [],
    ): ModListItem {
        // Resolve current membership once so neither the dependency filter nor
        // the capacity projection issues an existence query per candidate.
        $existingModIds = $this->existingModIds($modList);

        $deps = ($dependenciesToAdd instanceof Collection ? $dependenciesToAdd : collect($dependenciesToAdd))
            ->reject(fn (Mod $candidate): bool => $candidate->id === $mod->id)
            ->reject(fn (Mod $candidate): bool => isset($existingModIds[$candidate->id]))
            ->unique('id')
            ->values();

        $projectedCount = $modList->itemCount()
            + (isset($existingModIds[$mod->id]) ? 0 : 1)
            + $deps->count();

        $this->assertWithinCapacity($modList, $projectedCount);

        return DB::transaction(function () use ($modList, $mod, $deps): ModListItem {
            $position = $this->nextPosition($modList);

            $primary = $this->createItem($modList, Mod::class, $mod->id, $position);

            foreach ($deps as $dep) {
                $this->createItem($modList, Mod::class, $dep->id, ++$position);
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
        bool $includeParentMod = false,
    ): ModListItem {
        $needsParent = $addon->mod_id !== null && ! $modList->containsMod($addon->mod_id);

        throw_if($needsParent && ! $includeParentMod, ParentModMissingException::class, $modList, $addon);

        $projectedCount = $modList->itemCount()
            + ($modList->containsAddon($addon->id) ? 0 : 1)
            + ($needsParent ? 1 : 0);

        $this->assertWithinCapacity($modList, $projectedCount);

        return DB::transaction(function () use ($modList, $addon, $needsParent): ModListItem {
            $position = $this->nextPosition($modList);

            if ($needsParent && $addon->mod_id !== null) {
                $this->createItem($modList, Mod::class, $addon->mod_id, $position++);
            }

            return $this->createItem($modList, Addon::class, $addon->id, $position);
        });
    }

    /**
     * Update (or clear) the per-item curator note.
     */
    public function updateNote(ModListItem $item, ?string $note): void
    {
        $item->note = $note;
        $item->save();
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
        $this->createItem($favourites, Mod::class, $mod->id, $this->nextPosition($favourites));

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
     * Reorder a subset of top-level mod items relative to one another.
     *
     * Only the position slots already occupied by the supplied mod items are
     * rewritten, so reordering a paginated subset never disturbs the positions
     * of items that are not in the subset (e.g. items on other pages). All
     * position updates are flushed in a single upsert statement.
     *
     * @param  array<int, int>  $orderedModIds
     */
    public function reorderWithinPositions(ModList $modList, array $orderedModIds): void
    {
        $items = ModListItem::query()
            ->where('mod_list_id', $modList->id)
            ->where('listable_type', Mod::class)
            ->whereIn('listable_id', $orderedModIds)
            ->get()
            ->keyBy('listable_id');

        $slots = $items
            ->pluck('position')
            ->sort()
            ->values()
            ->all();

        /** @var array<int, array{id: int, mod_list_id: int, listable_type: string, listable_id: int, position: int}> $rows */
        $rows = [];
        foreach ($orderedModIds as $index => $modId) {
            $item = $items->get($modId);
            if ($item === null) {
                continue;
            }

            if (! isset($slots[$index])) {
                continue;
            }

            $rows[] = [
                'id' => $item->id,
                'mod_list_id' => $item->mod_list_id,
                'listable_type' => $item->listable_type,
                'listable_id' => $item->listable_id,
                'position' => $slots[$index],
            ];
        }

        if ($rows !== []) {
            ModListItem::query()->upsert($rows, ['id'], ['position']);
        }
    }

    /**
     * Resolve the suggested dependency mods to cascade when adding a mod to the list.
     *
     * Walks the dependency graph breadth-first so a dependency's own dependencies
     * are surfaced in the same prompt. Cycles and mods already on the list are
     * skipped via a shared visited set.
     *
     * @return Collection<int, Mod>
     */
    public function suggestedDependencies(ModList $modList, Mod $mod): Collection
    {
        $visited = $this->existingModIds($modList);
        $visited[$mod->id] = $mod->id;

        /** @var Collection<int, Mod> $mods */
        $mods = new Collection;
        $queue = [$mod];

        while ($queue !== []) {
            $current = array_shift($queue);

            $modVersion = $this->resolveModVersion($modList, $current);
            if (! $modVersion instanceof ModVersion) {
                continue;
            }

            $deps = $modVersion->latestDependenciesResolved()
                ->with('mod:id,name,slug,thumbnail,thumbnail_hash,owner_id')
                ->get();

            foreach ($deps as $depVersion) {
                $depMod = $depVersion->mod;

                if (isset($visited[$depMod->id])) {
                    continue;
                }

                $visited[$depMod->id] = $depMod->id;
                $mods->push($depMod);
                $queue[] = $depMod;
            }
        }

        return $mods;
    }

    /**
     * Resolve which ModVersion a list should display for the given mod and
     * whether that pick is incompatible with the list's target SPT version.
     *
     * Delegates to the bulk resolver so the single-mod and page-level paths
     * share one selection rule.
     */
    public function resolveListVersion(ModList $modList, Mod $mod): ResolvedListVersion
    {
        $resolved = $this->resolveListVersions($modList, new Collection([$mod]))->get($mod->id);

        return $resolved instanceof ResolvedListVersion
            ? $resolved
            : new ResolvedListVersion(null, false);
    }

    /**
     * Resolve the displayed ModVersion (and incompatibility flag) for a page
     * of mods in a bounded number of queries.
     *
     * When the list has no target SPT version, every mod resolves to its
     * `latestVersion` with no additional queries and `isIncompatible` false.
     *
     * When the list has a target SPT version, two queries are issued:
     *   1. The newest version of each mod that has the target SPT linked
     *      via the pivot (an exact compatibility match).
     *   2. For mods missing an exact match, the newest version whose
     *      `latestSptVersion` is the nearest-lower-or-equal SPT to the
     *      target.
     * Mods that have no version with any SPT ≤ target fall back to
     * `latestVersion` and are flagged incompatible.
     *
     * @param  Collection<int, Mod>  $mods
     * @return Collection<int, ResolvedListVersion> keyed by mod id
     */
    public function resolveListVersions(ModList $modList, Collection $mods): Collection
    {
        if ($mods->isEmpty()) {
            return new Collection;
        }

        $sptVersionId = $modList->spt_version_id;
        if ($sptVersionId === null) {
            return $mods->mapWithKeys(function (Mod $mod): array {
                $mod->loadMissing('latestVersion');

                return [$mod->id => new ResolvedListVersion($mod->latestVersion, false)];
            });
        }

        $modList->loadMissing('sptVersion');
        $targetSptVersion = $modList->sptVersion instanceof SptVersion ? $modList->sptVersion : null;

        /** @var array<int, int> $modIds */
        $modIds = $mods->pluck('id')->all();

        $exactMatches = $this->bulkExactMatches($modIds, $sptVersionId);

        /** @var array<int, int> $missingModIds */
        $missingModIds = array_values(array_diff($modIds, $exactMatches->keys()->all()));

        $closestMatches = new Collection;
        if ($missingModIds !== [] && $targetSptVersion instanceof SptVersion) {
            $closestMatches = $this->bulkClosestMatches($missingModIds, $targetSptVersion);
        }

        return $mods->mapWithKeys(function (Mod $mod) use ($exactMatches, $closestMatches, $targetSptVersion): array {
            $exact = $exactMatches->get($mod->id);
            if ($exact instanceof ModVersion) {
                // Exact pivot match: the card badge shows the list's target SPT, not the resolved version's
                // `latestSptVersion`, so a version that also supports newer SPTs does not get a higher badge than the
                // list it sits on.
                return [$mod->id => new ResolvedListVersion($exact, false, $targetSptVersion)];
            }

            $closest = $closestMatches->get($mod->id);
            if ($closest instanceof ModVersion) {
                return [$mod->id => new ResolvedListVersion($closest, true)];
            }

            $mod->loadMissing('latestVersion');

            return [$mod->id => new ResolvedListVersion($mod->latestVersion, true)];
        });
    }

    /**
     * Whether any top-level mod on the list lacks a version compatible with
     * the list's target SPT version. Always false when the list has no
     * target. A single existence query, scoped to the whole list (not just
     * the current page).
     */
    public function listHasIncompatibleMods(ModList $modList): bool
    {
        if ($modList->spt_version_id === null) {
            return false;
        }

        return Mod::query()
            ->whereIn('id', ModListItem::query()
                ->where('mod_list_id', $modList->id)
                ->where('listable_type', Mod::class)
                ->select('listable_id'))
            ->whereDoesntHave('versions', fn (Builder $q): Builder => $q
                ->where('disabled', false)
                ->whereHas('sptVersions', fn (Builder $s): Builder => $s->where('spt_versions.id', $modList->spt_version_id)))
            ->exists();
    }

    /**
     * Pick the ModVersion a list would show for a given mod, for callers
     * that only need the version (e.g. dependency resolution).
     */
    private function resolveModVersion(ModList $modList, Mod $mod): ?ModVersion
    {
        return $this->resolveListVersion($modList, $mod)->version;
    }

    /**
     * For each given mod id, the newest version that has the target SPT
     * version linked via the pivot (an exact compatibility match).
     *
     * @param  array<int, int>  $modIds
     * @return Collection<int, ModVersion> keyed by mod id
     */
    private function bulkExactMatches(array $modIds, int $sptVersionId): Collection
    {
        return ModVersion::query()
            ->where('disabled', false)
            ->whereIn('mod_id', $modIds)
            ->whereHas('sptVersions', fn (Builder $q): Builder => $q->where('spt_versions.id', $sptVersionId))
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get()
            ->groupBy('mod_id')
            ->map(fn (Collection $versions): ModVersion => $versions->first() ?? throw new LogicException('Grouped collection cannot be empty'));
    }

    /**
     * For each given mod id, the newest version whose `latestSptVersion` is
     * the nearest-lower-or-equal SPT to the target.
     *
     * @param  array<int, int>  $modIds
     * @return Collection<int, ModVersion> keyed by mod id
     */
    private function bulkClosestMatches(array $modIds, SptVersion $target): Collection
    {
        return ModVersion::query()
            ->where('disabled', false)
            ->whereIn('mod_id', $modIds)
            ->whereHas('latestSptVersion', fn (Builder $q): Builder => $q->whereRaw(
                '(spt_versions.version_major, spt_versions.version_minor, spt_versions.version_patch) <= (?, ?, ?)',
                [$target->version_major, $target->version_minor, $target->version_patch],
            ))
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get()
            ->groupBy('mod_id')
            ->map(fn (Collection $versions): ModVersion => $versions->first() ?? throw new LogicException('Grouped collection cannot be empty'));
    }

    /**
     * Load the ids of every mod already present on the list, keyed for O(1) lookup.
     *
     * @return array<int, int>
     */
    private function existingModIds(ModList $modList): array
    {
        /** @var array<int, int> $ids */
        $ids = ModListItem::query()
            ->where('mod_list_id', $modList->id)
            ->where('listable_type', Mod::class)
            ->pluck('listable_id')
            ->flip()
            ->all();

        return $ids;
    }

    /**
     * @throws ModListCapacityExceededException
     */
    private function assertWithinCapacity(ModList $modList, int $projectedCount): void
    {
        $max = ModList::maxItemsPerList();

        throw_if($projectedCount > $max, ModListCapacityExceededException::class, $modList, $projectedCount, $max);
    }

    /**
     * Internal helper to create (or no-op on duplicate) a list item at a position.
     */
    private function createItem(
        ModList $modList,
        string $listableType,
        int $listableId,
        int $position,
    ): ModListItem {
        /** @var ModListItem $item */
        $item = ModListItem::query()->firstOrCreate(
            [
                'mod_list_id' => $modList->id,
                'listable_type' => $listableType,
                'listable_id' => $listableId,
            ],
            [
                'position' => $position,
            ],
        );

        return $item;
    }

    /**
     * Resolve the next free position slot for the list (one past the current max).
     */
    private function nextPosition(ModList $modList): int
    {
        /** @var int|null $max */
        $max = ModListItem::query()
            ->where('mod_list_id', $modList->id)
            ->max('position');

        return ($max ?? 0) + 1;
    }
}
