<?php

declare(strict_types=1);

use App\Exceptions\ModListCapacityExceededException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\ModListService;
use App\Support\DataTransferObjects\ResolvedListVersion;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    public ModList $modList;

    public ?string $shareToken = null;

    /**
     * Latest status message announced to assistive technology via an
     * aria-live region after a list mutation (e.g. removing an item).
     */
    public string $statusMessage = '';

    /**
     * The id of the list item whose note is currently being edited inline, or
     * null when no note editor is open.
     */
    public ?int $editingNoteItemId = null;

    /**
     * Working copy of the note text bound to the open inline note editor.
     */
    public string $noteDraft = '';

    public function mount(int $listId, string $slug, ?string $shareToken = null): void
    {
        $this->modList = ModList::query()
            ->with([
                'owner:id,name',
                'sptVersion',
                'forkedFromList:id,owner_id,title,slug,visibility,disabled,share_token',
                'forkedFromList.owner:id,name',
            ])
            ->withCount([
                'publicForks as public_forks_count',
            ])
            ->findOrFail($listId);

        $this->shareToken = $shareToken;

        if ($this->modList->slug !== $slug) {
            $this->redirectRoute(
                $shareToken ? 'list.show.shared' : 'list.show',
                array_filter([
                    'listId' => $this->modList->id,
                    'slug' => $this->modList->slug,
                    'shareToken' => $shareToken,
                ]),
            );

            return;
        }

        Gate::authorize('view', $this->modList);
    }

    /**
     * Whether the viewer can manage this list (owner).
     */
    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()?->id === $this->modList->owner_id;
    }

    /**
     * Lightweight, list-wide item rows used for counts and dependency
     * membership checks. Only the columns needed for grouping are selected so
     * the whole list can be summarized cheaply, independent of the paginated
     * render query.
     *
     * @return EloquentCollection<int, ModListItem>
     */
    #[Computed]
    public function listItemRows(): EloquentCollection
    {
        return $this->modList->items()
            ->select(['id', 'mod_list_id', 'listable_type', 'listable_id', 'position'])
            ->get();
    }

    /**
     * Group cards (mods with nested addons) for the entire list.
     *
     * Lists are capped (see ModList::maxItemsPerList) so the whole list renders
     * in a single pass. This keeps drag-and-drop reorder working across the
     * full list without page boundaries.
     *
     * @return Collection<int, array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>, group_key: int|string, is_sortable: bool, resolved_version: ?ModVersion, version_incompatible: bool, display_spt_version: ?SptVersion}>
     */
    #[Computed]
    public function grouped(): Collection
    {
        $this->modList->setRelation('items', $this->loadAllItems());

        // Resolve the version each mod card should display against the list's
        // target SPT version (bulk, N+1-safe). Returns latestVersion across the
        // board when the list has no target SPT.
        //
        // Orphan-addon group anchors render the addon's parent mod as the card,
        // so their parent mod must also be fed to the resolver or the card
        // falls through to the mod's unfiltered latestVersion.
        $listMods = $this->modList->items
            ->map(function (ModListItem $item): ?Mod {
                if ($item->listable instanceof Mod) {
                    return $item->listable;
                }

                if ($item->listable instanceof Addon && $item->listable->mod instanceof Mod) {
                    return $item->listable->mod;
                }

                return null;
            })
            ->filter()
            ->unique('id')
            ->values();

        $resolvedVersions = resolve(ModListService::class)->resolveListVersions($this->modList, $listMods);

        // Batch-load the badge and dependency relations on the resolved
        // versions in one pass so the per-card render stays lazy-load free.
        $loadableVersions = $resolvedVersions
            ->map(fn (ResolvedListVersion $resolved): ?ModVersion => $resolved->version)
            ->filter()
            ->values();
        if ($loadableVersions->isNotEmpty()) {
            EloquentCollection::make($loadableVersions->all())->loadMissing([
                'latestSptVersion',
                'latestDependenciesResolved.mod:id,name,slug',
            ]);
        }

        // Each group carries its own render-time derivations (a stable key, whether it is drag-sortable, and the
        // version the card should show) so the Blade template stays logic-free.
        $canManage = $this->canManage;

        return $this->modList->groupedItems()
            ->values()
            ->map(function (array $group) use ($canManage, $resolvedVersions): array {
                $modItem = $group['mod_item'];
                $firstAddon = $group['addons']->first();
                $firstAddonId = $firstAddon instanceof ModListItem ? $firstAddon->id : 0;

                $resolved = $group['mod'] instanceof Mod
                    ? $resolvedVersions->get($group['mod']->id)
                    : null;

                return [
                    'mod' => $group['mod'],
                    'mod_item' => $modItem,
                    'addons' => $group['addons'],
                    'group_key' => $modItem instanceof ModListItem
                        ? $modItem->id
                        : 'detached-'.$firstAddonId,
                    'is_sortable' => $canManage
                        && $group['mod'] instanceof Mod
                        && $modItem instanceof ModListItem,
                    'resolved_version' => $resolved instanceof ResolvedListVersion ? $resolved->version : null,
                    'version_incompatible' => $resolved instanceof ResolvedListVersion && $resolved->isIncompatible,
                    'display_spt_version' => $resolved instanceof ResolvedListVersion ? $resolved->displaySptVersion : null,
                ];
            });
    }

    /**
     * Remove an item (mod or addon) from the list.
     */
    public function removeItem(int $itemId, ModListService $service): void
    {
        Gate::authorize('removeItem', $this->modList);

        /** @var ModListItem|null $item */
        $item = $this->modList->items()->with('listable')->find($itemId);
        if ($item === null) {
            return;
        }

        $removedName = $item->listable?->name;

        $service->removeItem($this->modList, $item);

        $this->statusMessage = $removedName === null
            ? __('Item removed from list.')
            : __('Removed :name from list.', ['name' => $removedName]);

        unset($this->grouped, $this->listItemRows, $this->hasIncompatibleMods);
    }

    /**
     * Add every missing dependency mod to the list in one transaction.
     *
     * The set is recomputed at action time rather than trusting the rendered
     * snapshot so a concurrent edit (e.g. the owner added a mod in another
     * tab) cannot smuggle stale ids past the capacity check.
     */
    public function addMissingDependencies(ModListService $service): void
    {
        Gate::authorize('addItem', $this->modList);

        $missing = $service->missingDependenciesForList($this->modList);
        if ($missing->isEmpty()) {
            $this->dispatch('modal-close', name: 'list-missing-dependencies-'.$this->modList->id);

            return;
        }

        try {
            $added = $service->addMods($this->modList, $missing);
        } catch (ModListCapacityExceededException) {
            $max = ModList::maxItemsPerList();

            Flux::toast(
                heading: __('List full'),
                text: __('Adding the missing dependencies would exceed the :count item cap. Remove items or use another list.', ['count' => $max]),
                variant: 'warning',
            );

            return;
        }

        $this->statusMessage = trans_choice(
            ':count missing dependency added to list.|:count missing dependencies added to list.',
            $added,
            ['count' => $added],
        );

        Flux::toast(
            heading: __('Dependencies added'),
            text: trans_choice(
                'Added :count missing dependency to the list.|Added :count missing dependencies to the list.',
                $added,
                ['count' => $added],
            ),
            variant: 'success',
        );

        $this->dispatch('modal-close', name: 'list-missing-dependencies-'.$this->modList->id);

        unset($this->grouped, $this->listItemRows, $this->hasIncompatibleMods, $this->missingDependencies, $this->dependencyModIds, $this->listModIds);
    }

    /**
     * Open the inline note editor for a list item.
     */
    public function startEditingNote(int $itemId): void
    {
        Gate::authorize('updateItemNote', $this->modList);

        /** @var ModListItem|null $item */
        $item = $this->modList->items()->find($itemId);
        if ($item === null) {
            return;
        }

        $this->editingNoteItemId = $item->id;
        $this->noteDraft = (string) $item->note;
        $this->resetErrorBag('noteDraft');
    }

    /**
     * Close the inline note editor without saving.
     */
    public function cancelEditingNote(): void
    {
        $this->reset(['editingNoteItemId', 'noteDraft']);
        $this->resetErrorBag('noteDraft');
    }

    /**
     * Persist the edited note. An empty draft clears the note.
     */
    public function saveNote(ModListService $service): void
    {
        if ($this->editingNoteItemId === null) {
            return;
        }

        Gate::authorize('updateItemNote', $this->modList);

        $this->validate([
            'noteDraft' => ['nullable', 'string', 'max:'.config()->integer('mod-lists.validation.note_max', 280)],
        ]);

        /** @var ModListItem|null $item */
        $item = $this->modList->items()->find($this->editingNoteItemId);
        if ($item === null) {
            return;
        }

        $note = mb_trim($this->noteDraft);
        $service->updateNote($item, $note === '' ? null : $note);

        $this->statusMessage = $note === '' ? __('Note removed.') : __('Note updated.');

        $this->reset(['editingNoteItemId', 'noteDraft']);

        unset($this->grouped);
    }

    /**
     * Reorder top-level mods via the drag-drop handler.
     *
     * Livewire's wire:sort calls this with the dragged mod id and its new
     * zero-based position. The list's mod items are reordered relative to one
     * another and persisted back onto their existing position slots.
     */
    public function reorder(int $modId, int $position, ModListService $service): void
    {
        Gate::authorize('reorder', $this->modList);

        $modIds = $this->grouped
            ->map(fn (array $group): ?ModListItem => $group['mod_item'])
            ->filter()
            ->map(fn (ModListItem $item): int => $item->listable_id)
            ->values()
            ->all();

        $movedFrom = array_search($modId, $modIds, true);
        if ($movedFrom === false) {
            return;
        }

        array_splice($modIds, $movedFrom, 1);
        array_splice($modIds, $position, 0, [$modId]);

        $service->reorderWithinPositions($this->modList, $modIds);

        $this->statusMessage = __('List order updated.');

        unset($this->grouped, $this->listItemRows);
    }

    /**
     * Regenerate the share token (hidden lists only).
     */
    public function regenerateShareToken(): void
    {
        Gate::authorize('regenerateShareToken', $this->modList);

        $this->modList->share_token = ModList::generateShareToken();
        $this->modList->save();

        $this->shareToken = $this->modList->share_token;
    }

    /**
     * List-wide item counts (mods + addons) for the summary line.
     *
     * Derived from the lightweight list-wide row set so the totals reflect the
     * whole list rather than the current page.
     *
     * @return array{mods: int, addons: int}
     */
    #[Computed]
    public function itemCounts(): array
    {
        $rows = $this->listItemRows;

        return [
            'mods' => $rows->where('listable_type', Mod::class)->count(),
            'addons' => $rows->where('listable_type', Addon::class)->count(),
        ];
    }

    /**
     * Mod IDs that are a dependency of another top-level mod in this list.
     *
     * Computed across every rendered group on the list.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function dependencyModIds(): Collection
    {
        $ids = [];

        foreach ($this->grouped as $group) {
            if ($group['mod_item'] === null) {
                continue;
            }

            $version = $group['resolved_version'];
            if (! $version instanceof ModVersion) {
                continue;
            }

            foreach ($version->latestDependenciesResolved as $depVersion) {
                $ids[$depVersion->mod_id] = $depVersion->mod_id;
            }
        }

        return new Collection(array_values($ids));
    }

    /**
     * Mod IDs that are top-level mod items anywhere in this list.
     *
     * Derived from the lightweight list-wide row set used by the dependency
     * badge's "present on list" check.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function listModIds(): Collection
    {
        /** @var Collection<int, int> */
        return $this->listItemRows
            ->where('listable_type', Mod::class)
            ->pluck('listable_id')
            ->values();
    }

    /**
     * Whether the list has a target SPT version and at least one mod on the
     * whole list lacks a version compatible with that target. Drives the
     * list-level "contains incompatible mods" warning callout.
     */
    #[Computed]
    public function hasIncompatibleMods(): bool
    {
        return resolve(ModListService::class)->listHasIncompatibleMods($this->modList);
    }

    /**
     * Dependency mods that the list's existing mods require but which are not
     * themselves on the list. Walks the dependency graph from every top-level
     * mod on the list so transitive dependencies surface too.
     *
     * Only computed for the list owner since it powers an owner-only action.
     *
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function missingDependencies(): Collection
    {
        if (! $this->canManage) {
            return new Collection;
        }

        return resolve(ModListService::class)->missingDependenciesForList($this->modList);
    }

    /**
     * The source list this list was forked or duplicated from, or null when the source has been deleted or this list
     * is not a fork.
     */
    #[Computed]
    public function forkedFromSource(): ?ModList
    {
        if ($this->modList->forked_from_list_id === null) {
            return null;
        }

        $source = $this->modList->forkedFromList;

        return $source instanceof ModList ? $source : null;
    }

    /**
     * Whether the current viewer can view the source list, gating whether the provenance chip renders as a link versus
     * a plain attribution.
     */
    #[Computed]
    public function forkedFromViewable(): bool
    {
        $source = $this->forkedFromSource;

        return $source instanceof ModList && Gate::allows('view', $source);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'grouped' => $this->grouped,
            'canManage' => $this->canManage,
            'itemCounts' => $this->itemCounts,
            'dependencyModIds' => $this->dependencyModIds,
            'listModIds' => $this->listModIds,
            'hasIncompatibleMods' => $this->hasIncompatibleMods,
            'missingDependencies' => $this->missingDependencies,
            'forkedFromSource' => $this->forkedFromSource,
            'forkedFromViewable' => $this->forkedFromViewable,
        ];
    }

    /**
     * Eager-load every item on the list with the heavy nested relations needed
     * to render its group card.
     *
     * @return EloquentCollection<int, ModListItem>
     */
    private function loadAllItems(): EloquentCollection
    {
        /** @var EloquentCollection<int, ModListItem> $items */
        $items = $this->modList->items()
            ->with(['listable' => $this->listableMorphConstraint()])
            ->get();

        return $items;
    }

    /**
     * Eager-load constraint for the polymorphic listable relation, scoping the
     * heavy nested relations loaded for each Mod / Addon group card.
     *
     * @return Closure(Relation<*, *, *>):void
     */
    private function listableMorphConstraint(): Closure
    {
        return function (Relation $relation): void {
            if (! $relation instanceof MorphTo) {
                return;
            }

            $relation->morphWith([
                Mod::class => ['owner:id,name', 'latestVersion', 'latestVersion.latestSptVersion', 'latestVersion.latestDependenciesResolved.mod:id,name,slug'],
                Addon::class => [
                    'owner:id,name',
                    'latestVersion',
                    'mod',
                    'mod.owner:id,name',
                    'mod.latestVersion',
                    'mod.latestVersion.latestSptVersion',
                    'mod.latestVersion.latestDependenciesResolved.mod:id,name,slug',
                ],
            ]);
        };
    }
};
