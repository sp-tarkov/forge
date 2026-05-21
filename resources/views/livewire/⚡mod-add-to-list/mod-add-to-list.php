<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Services\ModListService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Add-to-list modal shared by the mod and addon detail pages.
 *
 * The `sourceType` property identifies whether the modal was triggered from a
 * mod or an addon. Existing lists can be toggled inline, a new list can be
 * created on the spot, and adding a mod with unmet dependencies routes through
 * a per-dependency checkbox step before the write happens.
 */
new class extends Component
{
    public int $sourceId;

    public string $sourceType = 'mod';

    public string $search = '';

    public bool $creatingNew = false;

    public string $newTitle = '';

    public string $newVisibility = 'private';

    public ?int $activeListId = null;

    public string $note = '';

    public bool $showDependencyStep = false;

    /**
     * @var array<int, int> Mod IDs checked for cascade
     */
    public array $selectedDependencyIds = [];

    public function mount(int $sourceId, string $sourceType = 'mod'): void
    {
        $this->sourceId = $sourceId;
        $this->sourceType = $sourceType;
    }

    /**
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
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();
    }

    /**
     * The dependency mods offered for the cascade step.
     *
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function suggestedDependencies(): Collection
    {
        if ($this->activeListId === null || $this->sourceType !== 'mod') {
            return new Collection;
        }

        $list = ModList::query()->find($this->activeListId);
        if ($list === null || $list->owner_id !== Auth::id()) {
            return new Collection;
        }

        $mod = Mod::query()->find($this->sourceId);
        if ($mod === null) {
            return new Collection;
        }

        return resolve(ModListService::class)->suggestedDependencies($list, $mod);
    }

    /**
     * Whether the given list already contains the source mod or addon.
     */
    public function membershipFor(int $listId): bool
    {
        $list = ModList::query()->find($listId);
        if ($list === null) {
            return false;
        }

        return $this->sourceType === 'mod'
            ? $list->containsMod($this->sourceId)
            : $list->containsAddon($this->sourceId);
    }

    /**
     * Add the source mod or addon to the chosen list.
     *
     * When the source is a mod with unmet dependencies, the first call opens
     * the dependency-cascade step instead of writing immediately.
     */
    public function addToList(int $listId, ModListService $service): void
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

        if ($this->sourceType === 'mod') {
            $mod = Mod::query()->findOrFail($this->sourceId);

            $suggested = $service->suggestedDependencies($list, $mod);

            if ($suggested->isNotEmpty() && ! $this->showDependencyStep) {
                $this->activeListId = $listId;
                $this->selectedDependencyIds = $suggested->map(fn (Mod $dep): int => (int) $dep->id)->all();
                $this->showDependencyStep = true;

                return;
            }

            $deps = $suggested->filter(fn (Mod $m): bool => in_array($m->id, $this->selectedDependencyIds, true));

            Gate::authorize('addItem', $list);

            try {
                $service->addMod($list, $mod, $this->note === '' ? null : $this->note, $deps);
            } catch (ModListCapacityExceededException) {
                $this->toastListFull();

                return;
            }

            $this->toastAdded($list);
            $this->resetSubFlow();

            return;
        }

        $addon = Addon::query()->findOrFail($this->sourceId);

        Gate::authorize('addItem', $list);

        try {
            $service->addAddon($list, $addon, $this->note === '' ? null : $this->note, includeParentMod: true);
        } catch (ModListCapacityExceededException) {
            $this->toastListFull();

            return;
        } catch (ParentModMissingException) {
            Flux::toast(
                heading: __('Could not add'),
                text: __('This addon needs its parent mod, which could not be added.'),
                variant: 'danger',
            );

            return;
        }

        $this->toastAdded($list);
        $this->resetSubFlow();
    }

    /**
     * Remove the source mod or addon from the chosen list.
     */
    public function removeFromList(int $listId, ModListService $service): void
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

        $item = $list->items()
            ->where('listable_type', $this->sourceType === 'mod' ? Mod::class : Addon::class)
            ->where('listable_id', $this->sourceId)
            ->first();

        if ($item !== null) {
            Gate::authorize('removeItem', $list);

            $service->removeItem($list, $item);

            Flux::toast(
                heading: __('Removed'),
                text: __('Removed from ":title".', ['title' => $list->title]),
                variant: 'success',
            );
        }
    }

    /**
     * Confirm the dependency-cascade step and complete the deferred add.
     */
    public function confirmDependencies(ModListService $service): void
    {
        if ($this->activeListId === null) {
            return;
        }

        $this->addToList($this->activeListId, $service);
    }

    /**
     * Dismiss the dependency-cascade step without writing anything.
     */
    public function cancelDependencyStep(): void
    {
        $this->resetSubFlow();
    }

    public function startCreatingNew(): void
    {
        $this->creatingNew = true;
    }

    public function cancelCreatingNew(): void
    {
        $this->creatingNew = false;
        $this->newTitle = '';
        $this->newVisibility = 'private';
    }

    /**
     * Create a new list inline and add the source mod or addon to it.
     */
    public function createAndAdd(ModListService $service): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->validate([
            'newTitle' => ['required', 'string', 'min:1', 'max:120'],
            'newVisibility' => ['required', Rule::enum(ListVisibility::class)],
        ]);

        Gate::authorize('create', ModList::class);

        $list = new ModList;
        $list->owner_id = $user->id;
        $list->title = mb_trim($this->newTitle);
        $list->visibility = ListVisibility::from($this->newVisibility);
        $list->save();

        $this->cancelCreatingNew();
        $this->addToList($list->id, $service);
    }

    private function resetSubFlow(): void
    {
        $this->showDependencyStep = false;
        $this->activeListId = null;
        $this->selectedDependencyIds = [];
        $this->note = '';

        unset($this->userLists);
    }

    private function toastAdded(ModList $list): void
    {
        Flux::toast(
            heading: __('Added'),
            text: __('Added to ":title".', ['title' => $list->title]),
            variant: 'success',
        );
    }

    private function toastListFull(): void
    {
        $max = config()->integer('mod-lists.max_items_per_list', 250);

        Flux::toast(
            heading: __('List full'),
            text: __('This list is full (:count items max). Remove an item or use another list.', ['count' => $max]),
            variant: 'warning',
        );
    }
};
