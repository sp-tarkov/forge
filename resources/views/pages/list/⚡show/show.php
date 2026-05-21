<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Services\ModListService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] class extends Component
{
    use WithPagination;

    /**
     * Number of group cards (parent mods + orphan-addon groups) shown per page.
     */
    private const int PER_PAGE = 24;

    /**
     * Query-string parameter name used for the items paginator.
     */
    private const string PAGE_NAME = 'page';

    public ModList $modList;

    public ?string $shareToken = null;

    public function mount(int $listId, string $slug, ?string $shareToken = null): void
    {
        $this->modList = ModList::query()
            ->with(['owner:id,name', 'sptVersion'])
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
     * Paginated group cards (mods with nested addons) for the current page.
     *
     * Pagination operates on the top-level group anchors (parent-mod items and
     * orphan-addon items). Heavy relations are eager-loaded only for the page
     * being rendered.
     *
     * @return LengthAwarePaginator<int, array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>}>
     */
    #[Computed]
    public function grouped(): LengthAwarePaginator
    {
        $rows = $this->listItemRows;

        $modParentIds = $rows
            ->where('listable_type', Mod::class)
            ->pluck('listable_id')
            ->all();

        $addonItemIds = $rows
            ->where('listable_type', Addon::class)
            ->pluck('id')
            ->all();

        $addonParentMap = $addonItemIds === []
            ? new Collection
            : Addon::query()
                ->select(['id', 'mod_id'])
                ->whereIn('id', $rows->where('listable_type', Addon::class)->pluck('listable_id'))
                ->get()
                ->keyBy('id');

        // A "group anchor" is either a top-level mod item, or an addon item
        // whose parent mod is not itself a top-level item on the list.
        $anchorRows = $rows->filter(function (ModListItem $row) use ($modParentIds, $addonParentMap): bool {
            if ($row->listable_type === Mod::class) {
                return true;
            }

            $parentModId = $addonParentMap->get($row->listable_id)?->mod_id;

            return $parentModId === null || ! in_array($parentModId, $modParentIds, true);
        })->values();

        $page = LengthAwarePaginator::resolveCurrentPage(self::PAGE_NAME);
        $perPage = self::PER_PAGE;
        $total = $anchorRows->count();

        /** @var array<int, int> $pageAnchorIds */
        $pageAnchorIds = $anchorRows
            ->forPage($page, $perPage)
            ->pluck('id')
            ->all();

        $this->modList->setRelation('items', $this->loadPageItems($pageAnchorIds));

        $groups = $this->modList->groupedItems()->values();

        /** @var LengthAwarePaginator<int, array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>}> $paginator */
        $paginator = new LengthAwarePaginator(
            $groups,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => self::PAGE_NAME,
            ],
        );

        return $paginator;
    }

    /**
     * Eager-load the fully hydrated item rows (with heavy nested relations) for
     * the group anchors on the current page, plus the addon items belonging to
     * each anchored parent mod.
     *
     * @param  array<int, int>  $anchorIds
     * @return EloquentCollection<int, ModListItem>
     */
    private function loadPageItems(array $anchorIds): EloquentCollection
    {
        if ($anchorIds === []) {
            return new EloquentCollection;
        }

        $anchorItems = $this->modList->items()
            ->whereIn('id', $anchorIds)
            ->get();

        $pageModIds = $anchorItems
            ->where('listable_type', Mod::class)
            ->pluck('listable_id')
            ->all();

        $addonItemIds = $pageModIds === []
            ? []
            : $this->modList->items()
                ->where('listable_type', Addon::class)
                ->whereIn('listable_id', Addon::query()->whereIn('mod_id', $pageModIds)->select('id'))
                ->pluck('id')
                ->all();

        /** @var array<int, int> $pageItemIds */
        $pageItemIds = $anchorItems->pluck('id')->merge($addonItemIds)->all();

        /** @var EloquentCollection<int, ModListItem> $items */
        $items = $this->modList->items()
            ->whereIn('id', $pageItemIds)
            ->with(['listable' => $this->listableMorphConstraint()])
            ->get();

        return $items;
    }

    /**
     * Eager-load constraint for the polymorphic listable relation, scoping the
     * heavy nested relations loaded for each Mod / Addon group card.
     *
     * @return \Closure(Relation<*, *, *>): void
     */
    private function listableMorphConstraint(): \Closure
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

    /**
     * Remove an item (mod or addon) from the list.
     */
    public function removeItem(int $itemId, ModListService $service): void
    {
        Gate::authorize('removeItem', $this->modList);

        /** @var ModListItem|null $item */
        $item = $this->modList->items()->find($itemId);
        if ($item === null) {
            return;
        }

        $service->removeItem($this->modList, $item);

        unset($this->grouped, $this->listItemRows);
    }

    /**
     * Reorder top-level mods on the current page via the drag-drop handler.
     *
     * Livewire's wire:sort calls this with the dragged mod id and its new
     * zero-based position within the page. The page's mod items are reordered
     * relative to one another and persisted back onto their existing position
     * slots, so items on other pages keep their positions intact.
     */
    public function reorder(int $modId, int $position, ModListService $service): void
    {
        Gate::authorize('reorder', $this->modList);

        $pageModIds = $this->grouped
            ->getCollection()
            ->map(fn (array $group): ?ModListItem => $group['mod_item'])
            ->filter()
            ->map(fn (ModListItem $item): int => $item->listable_id)
            ->values()
            ->all();

        $movedFrom = array_search($modId, $pageModIds, true);
        if ($movedFrom === false) {
            return;
        }

        array_splice($pageModIds, $movedFrom, 1);
        array_splice($pageModIds, $position, 0, [$modId]);

        $service->reorderWithinPositions($this->modList, $pageModIds);

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
     * Computed across the page's rendered groups. The badge reflects the live
     * resolved-dependency state of the mods visible on the current page.
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

            if ($group['mod'] === null) {
                continue;
            }

            $version = $group['mod']->latestVersion;
            if ($version === null) {
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
     * Derived from the lightweight list-wide row set so the dependency badge's
     * "present on list" check considers the entire list, not just this page.
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
        ];
    }
};
