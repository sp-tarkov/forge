<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ModListEntryDisabledException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\Scopes\PublishedScope;
use App\Models\SptVersion;
use App\Models\User;
use App\Support\DataTransferObjects\DependencyCascadeResult;
use App\Support\DataTransferObjects\ResolvedListVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Copy a source list into a brand-new list owned by the actor.
     *
     * The new list starts Private with comments disabled and tracks its origin through `forked_from_list_id`. Items are
     * bulk-inserted preserving their polymorphic identity, note, and position. Comments, reports, and moderation state
     * are intentionally not copied.
     *
     * @throws ModListCapacityExceededException
     */
    public function forkList(User $newOwner, ModList $source, string $title): ModList
    {
        $source->loadMissing('items');

        // Tombstones are an artifact of the source list losing items; a fork should start clean.
        $activeItems = $source->items->reject(fn (ModListItem $item): bool => $item->isTombstone())->values();

        $sourceItemCount = $activeItems->count();
        $maxItems = ModList::maxItemsPerList();
        throw_if(
            $sourceItemCount > $maxItems,
            ModListCapacityExceededException::class,
            $source,
            $sourceItemCount,
            $maxItems,
        );

        return DB::transaction(function () use ($newOwner, $source, $title, $activeItems): ModList {
            $list = new ModList;
            $list->owner_id = $newOwner->id;
            $list->title = mb_trim($title);
            $list->description = $source->description;
            $list->visibility = ListVisibility::Private;
            $list->spt_version_id = $source->spt_version_id;
            $list->is_default = false;
            // Private lists never surface a comment thread.
            $list->comments_disabled = true;
            $list->forked_from_list_id = $source->id;
            $list->share_token = null;

            if ($source->thumbnail !== null && $source->thumbnail !== '') {
                $copiedThumbnail = $this->copyThumbnailFile($source->thumbnail);
                if ($copiedThumbnail !== null) {
                    $list->thumbnail = $copiedThumbnail;
                    $list->thumbnail_hash = $source->thumbnail_hash;
                }
            }

            $list->save();

            if ($activeItems->isNotEmpty()) {
                $now = now();
                $rows = $activeItems->map(fn (ModListItem $item): array => [
                    'mod_list_id' => $list->id,
                    'listable_type' => $item->listable_type,
                    'listable_id' => $item->listable_id,
                    'note' => $item->note,
                    'position' => $item->position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                ModListItem::query()->insert($rows);
            }

            return $list;
        });
    }

    /**
     * Add a mod to the list, optionally cascading its dependencies.
     *
     * @param  Collection<int, Mod>|array<int, Mod>  $dependenciesToAdd
     *
     * @throws ModListCapacityExceededException
     * @throws ModListEntryDisabledException
     */
    public function addMod(
        ModList $modList,
        Mod $mod,
        Collection|array $dependenciesToAdd = [],
    ): ModListItem {
        // Favourites bypass the author opt-out: users keep full control of their personal Favourites list.
        throw_if(! $modList->is_default && $mod->lists_disabled, ModListEntryDisabledException::class, $modList, $mod);

        // Resolve current membership once so neither the dependency filter nor
        // the capacity projection issues an existence query per candidate.
        $existingModIds = $this->existingModIds($modList);

        $deps = ($dependenciesToAdd instanceof Collection ? $dependenciesToAdd : collect($dependenciesToAdd))
            ->reject(fn (Mod $candidate): bool => $candidate->id === $mod->id)
            ->reject(fn (Mod $candidate): bool => isset($existingModIds[$candidate->id]))
            ->reject(fn (Mod $candidate): bool => ! $modList->is_default && $candidate->lists_disabled)
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
     * @throws ModListEntryDisabledException
     */
    public function addAddon(
        ModList $modList,
        Addon $addon,
        bool $includeParentMod = false,
    ): ModListItem {
        // Inheritance: if the parent mod opts out of lists, its addons are blocked too. Favourites bypass this,
        // matching the addMod behaviour above.
        if (! $modList->is_default) {
            $parent = $addon->mod_id !== null
                ? ($addon->relationLoaded('mod') ? $addon->mod : Mod::query()->find($addon->mod_id))
                : null;
            throw_if($parent instanceof Mod && $parent->lists_disabled, ModListEntryDisabledException::class, $modList, $addon);
        }

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
     * Toggle a mod in the user's Favourites list without the dependency prompt. Returns true if the mod was added,
     * false if it was removed.
     *
     * Favourites intentionally bypass the author lists_disabled opt-out: users keep full control of their own
     * personal Favourites list.
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
     * Remove an item from a list. Cascades addons when a parent mod is removed. The cascade bypasses the published
     * scope so addon items whose addons are unpublished or hidden from the viewer are removed along with the mod.
     */
    public function removeItem(ModList $modList, ModListItem $item): void
    {
        DB::transaction(function () use ($modList, $item): void {
            if ($item->listable_type === Mod::class) {
                ModListItem::query()
                    ->where('mod_list_id', $modList->id)
                    ->where('listable_type', Addon::class)
                    ->whereIn('listable_id', Addon::query()
                        ->withoutGlobalScope(PublishedScope::class)
                        ->where('mod_id', $item->listable_id)
                        ->pluck('id'))
                    ->delete();
            }

            $item->delete();
        });
    }

    /**
     * Reorder a subset of top-level mod items relative to one another.
     *
     * Only the position slots already occupied by the supplied mod items are
     * rewritten, so items omitted from the subset keep their existing
     * positions. All position updates are flushed in a single upsert statement.
     *
     * @param  array<int, int>  $orderedModIds
     */
    public function reorderWithinPositions(ModList $modList, array $orderedModIds): void
    {
        // Tombstones keep their original position and are not reorderable.
        $items = ModListItem::query()
            ->active()
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
        return $this->suggestedDependenciesResult($modList, $mod)->included;
    }

    /**
     * Same as suggestedDependencies but also returns the dependency mods that were skipped because their author
     * opted out of mod lists, so the caller can surface a toast.
     */
    public function suggestedDependenciesResult(ModList $modList, Mod $mod): DependencyCascadeResult
    {
        return $this->walkDependencyGraph($modList, new Collection([$mod]));
    }

    /**
     * Resolve every dependency mod that the list's existing mods depend on
     * but which isn't itself on the list.
     *
     * Walks the dependency graph from every top-level mod currently on the list
     * so transitive dependencies-of-dependencies are picked up too.
     *
     * @return Collection<int, Mod>
     */
    public function missingDependenciesForList(ModList $modList): Collection
    {
        return $this->missingDependenciesResultForList($modList)->included;
    }

    /**
     * Same as missingDependenciesForList but also returns the dependency mods that were skipped because their author
     * opted out of mod lists.
     */
    public function missingDependenciesResultForList(ModList $modList): DependencyCascadeResult
    {
        /** @var Collection<int, Mod> $topLevel */
        $topLevel = Mod::query()
            ->whereIn('id', ModListItem::query()
                ->active()
                ->where('mod_list_id', $modList->id)
                ->where('listable_type', Mod::class)
                ->select('listable_id'))
            ->get();

        if ($topLevel->isEmpty()) {
            return new DependencyCascadeResult(new Collection, new Collection);
        }

        return $this->walkDependencyGraph($modList, $topLevel);
    }

    /**
     * Add many dependency mods to the list in one transaction.
     *
     * Skips mods already present, enforces the per-list cap against the
     * projected count, and assigns sequential positions starting from the
     * next free slot. Returns the number of mods actually added.
     *
     * @param  Collection<int, Mod>|array<int, Mod>  $mods
     *
     * @throws ModListCapacityExceededException
     */
    public function addMods(ModList $modList, Collection|array $mods): int
    {
        $existingModIds = $this->existingModIds($modList);

        $toAdd = ($mods instanceof Collection ? $mods : collect($mods))
            ->reject(fn (Mod $candidate): bool => isset($existingModIds[$candidate->id]))
            ->reject(fn (Mod $candidate): bool => ! $modList->is_default && $candidate->lists_disabled)
            ->unique('id')
            ->values();

        if ($toAdd->isEmpty()) {
            return 0;
        }

        $this->assertWithinCapacity($modList, $modList->itemCount() + $toAdd->count());

        return DB::transaction(function () use ($modList, $toAdd): int {
            $position = $this->nextPosition($modList);

            foreach ($toAdd as $mod) {
                $this->createItem($modList, Mod::class, $mod->id, $position++);
            }

            return $toAdd->count();
        });
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
                ->active()
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
     * BFS walk of the dependency graph starting from a set of seed mods.
     *
     * Returns both the dependency mods that can be cascaded (included) and the ones that were skipped because their
     * author opted out of mod lists. Cycles, mods already on the list, and opted-out mods are tracked via a shared
     * visited set so each mod is considered at most once.
     *
     * Favourites bypass the opt-out: when the target list is favourites the skipped set is always empty.
     *
     * @param  Collection<int, Mod>  $startingMods
     */
    private function walkDependencyGraph(ModList $modList, Collection $startingMods): DependencyCascadeResult
    {
        $visited = $this->existingModIds($modList);

        foreach ($startingMods as $start) {
            $visited[$start->id] = $start->id;
        }

        /** @var Collection<int, Mod> $included */
        $included = new Collection;
        /** @var Collection<int, Mod> $skipped */
        $skipped = new Collection;
        $queue = $startingMods->values()->all();
        $respectOptOut = ! $modList->is_default;

        while ($queue !== []) {
            $current = array_shift($queue);

            $modVersion = $this->resolveModVersion($modList, $current);
            if (! $modVersion instanceof ModVersion) {
                continue;
            }

            $deps = $modVersion->latestDependenciesResolved()
                ->with('mod:id,name,slug,thumbnail,thumbnail_hash,owner_id,lists_disabled')
                ->get();

            foreach ($deps as $depVersion) {
                $depMod = $depVersion->mod;

                if (isset($visited[$depMod->id])) {
                    continue;
                }

                $visited[$depMod->id] = $depMod->id;

                if ($respectOptOut && $depMod->lists_disabled) {
                    // Opted-out mods are surfaced separately so the cascade UI can name them in a toast, but they are
                    // not traversed further: their own transitive dependencies are the author's problem, not ours.
                    $skipped->push($depMod);

                    continue;
                }

                $included->push($depMod);
                $queue[] = $depMod;
            }
        }

        return new DependencyCascadeResult($included, $skipped);
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
     * Load the ids of every mod actively present on the list, keyed for O(1) lookup. Tombstoned items are excluded
     * so dependency cascade and capacity projection treat them as absent.
     *
     * @return array<int, int>
     */
    private function existingModIds(ModList $modList): array
    {
        /** @var array<int, int> $ids */
        $ids = ModListItem::query()
            ->active()
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
     * Copy a list thumbnail to a new path on the configured asset disk.
     *
     * Returns the new storage path on success, or null when the source file is missing on disk so the fork falls back
     * to no thumbnail.
     */
    private function copyThumbnailFile(string $sourcePath): ?string
    {
        /** @var string $disk */
        $disk = config()->string('filesystems.asset_upload', 'public');
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $directory = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $newPath = ($directory === '' || $directory === '.' ? '' : $directory.'/').Str::random(40);

        if ($extension !== '') {
            $newPath .= '.'.$extension;
        }

        $storage->copy($sourcePath, $newPath);

        return $newPath;
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
