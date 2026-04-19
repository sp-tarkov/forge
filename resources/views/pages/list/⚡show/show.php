<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Services\ModListService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
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
     * Get grouped list items (mods with nested addons).
     *
     * @return Collection<string, array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>}>
     */
    #[Computed]
    public function grouped(): Collection
    {
        $this->modList->load([
            'items.listable' => function (MorphTo $morph): void {
                $morph->morphWith([
                    Mod::class => ['owner:id,name', 'latestVersion', 'latestVersion.latestSptVersion', 'latestVersion.latestDependenciesResolved.mod:id,name,slug'],
                    Addon::class => ['owner:id,name', 'latestVersion', 'mod:id,name,slug'],
                ]);
            },
        ]);

        return $this->modList->groupedItems();
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

        unset($this->grouped);
    }

    /**
     * Reorder top-level mods via drag-drop payload (array of mod IDs).
     *
     * @param  array<int, int>  $modIds
     */
    public function reorder(array $modIds, ModListService $service): void
    {
        Gate::authorize('reorder', $this->modList);

        $service->reorder($this->modList, $modIds);

        unset($this->grouped);
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
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'grouped' => $this->grouped,
            'canManage' => $this->canManage,
        ];
    }
};
