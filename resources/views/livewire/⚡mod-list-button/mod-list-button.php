<?php

declare(strict_types=1);

use App\Exceptions\ModListCapacityExceededException;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Services\ModListService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Unified heart button + list popover used on the mod show page.
 *
 * Replaces the older standalone favourite toggle. Lists every list the viewer
 * owns (Favourites pinned at the top); each row is a quick add/remove toggle.
 * When adding a mod that has unmet dependencies, a confirmation modal lets the
 * viewer decide whether to bring the dependencies along or skip them.
 */
new class extends Component
{
    public int $modId;

    public string $size = 'sm';

    /**
     * The list awaiting a dependency-cascade decision.
     */
    public ?int $pendingListId = null;

    /**
     * Drives the dependency-confirmation modal's visibility.
     */
    public bool $showDependencyModal = false;

    public function mount(int $modId, string $size = 'sm'): void
    {
        $this->modId = $modId;
        $this->size = $size;
    }

    /**
     * Reset the pending list whenever the dependency modal is dismissed
     * (e.g., the viewer pressed Esc or clicked the backdrop).
     */
    public function updatedShowDependencyModal(bool $value): void
    {
        if (! $value) {
            $this->pendingListId = null;
            unset($this->pendingDependencies);
        }
    }

    /**
     * The viewer's lists, ordered with Favourites first then alphabetically.
     *
     * @return Collection<int, ModList>
     */
    #[Computed]
    public function userLists(): Collection
    {
        $user = Auth::user();
        if ($user === null) {
            return new Collection;
        }

        return $user->modLists()
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();
    }

    /**
     * IDs of the viewer's lists that already contain this mod.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function listIdsContainingMod(): Collection
    {
        $user = Auth::user();
        if ($user === null) {
            return new Collection;
        }

        /** @var Collection<int, int> $ids */
        $ids = ModListItem::query()
            ->where('listable_type', Mod::class)
            ->where('listable_id', $this->modId)
            ->whereIn('mod_list_id', $user->modLists()->select('id'))
            ->pluck('mod_list_id');

        return $ids;
    }

    /**
     * Drives the heart button's filled (rose) state.
     */
    #[Computed]
    public function isOnAnyList(): bool
    {
        return $this->listIdsContainingMod->isNotEmpty();
    }

    /**
     * The dependencies the modal will offer when resolving the pending add.
     *
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function pendingDependencies(): Collection
    {
        if ($this->pendingListId === null) {
            return new Collection;
        }

        $user = Auth::user();
        $list = $user?->modLists()->find($this->pendingListId);
        $mod = Mod::query()->find($this->modId);
        if ($list === null || $mod === null) {
            return new Collection;
        }

        return resolve(ModListService::class)->suggestedDependencies($list, $mod);
    }

    /**
     * Add the mod to the list, or remove it if already present. When adding a
     * mod that has unmet dependencies, defer the actual write until the viewer
     * answers the confirmation modal.
     */
    public function toggleList(int $listId, ModListService $service): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        /** @var ModList|null $list */
        $list = $user->modLists()->find($listId);
        if ($list === null) {
            return;
        }

        $mod = Mod::query()->find($this->modId);
        if ($mod === null) {
            return;
        }

        if ($list->containsMod($mod->id)) {
            $item = $list->items()
                ->where('listable_type', Mod::class)
                ->where('listable_id', $mod->id)
                ->first();

            if ($item !== null) {
                $service->removeItem($list, $item);
                Flux::toast(
                    heading: __('Removed'),
                    text: __('Removed from ":title".', ['title' => $list->title]),
                    variant: 'success',
                );
                $this->resetMembershipCache();
            }

            return;
        }

        if ($service->suggestedDependencies($list, $mod)->isNotEmpty()) {
            $this->pendingListId = $listId;
            $this->showDependencyModal = true;
            unset($this->pendingDependencies);

            return;
        }

        $this->commitAdd($service, $list, $mod, includeDeps: false);
    }

    /**
     * Resolve the pending add by including all suggested dependencies.
     */
    public function addWithDependencies(ModListService $service): void
    {
        $this->resolvePending($service, includeDeps: true);
    }

    /**
     * Resolve the pending add by adding only the mod (dependencies skipped).
     */
    public function addIgnoringDependencies(ModListService $service): void
    {
        $this->resolvePending($service, includeDeps: false);
    }

    /**
     * Dismiss the dependency modal without writing anything.
     */
    public function cancelDependencyPrompt(): void
    {
        $this->showDependencyModal = false;
        $this->pendingListId = null;
        unset($this->pendingDependencies);
    }

    private function resolvePending(ModListService $service, bool $includeDeps): void
    {
        if ($this->pendingListId === null) {
            return;
        }

        $user = Auth::user();
        $list = $user?->modLists()->find($this->pendingListId);
        $mod = Mod::query()->find($this->modId);
        if ($list === null || $mod === null) {
            $this->cancelDependencyPrompt();

            return;
        }

        $this->commitAdd($service, $list, $mod, includeDeps: $includeDeps);
        $this->cancelDependencyPrompt();
    }

    private function commitAdd(ModListService $service, ModList $list, Mod $mod, bool $includeDeps): void
    {
        $deps = $includeDeps
            ? $service->suggestedDependencies($list, $mod)
            : new Collection;

        try {
            $service->addMod($list, $mod, null, $deps);
        } catch (ModListCapacityExceededException $modListCapacityExceededException) {
            Flux::toast(
                heading: __('List full'),
                text: $modListCapacityExceededException->getMessage(),
                variant: 'warning',
            );

            return;
        }

        Flux::toast(
            heading: __('Added'),
            text: $includeDeps && $deps->isNotEmpty()
                ? __('Added with dependencies to ":title".', ['title' => $list->title])
                : __('Added to ":title".', ['title' => $list->title]),
            variant: 'success',
        );
        $this->resetMembershipCache();
    }

    private function resetMembershipCache(): void
    {
        unset($this->listIdsContainingMod, $this->isOnAnyList, $this->userLists);
    }
};
