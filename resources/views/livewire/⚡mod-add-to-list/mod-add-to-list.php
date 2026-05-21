<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Exceptions\ParentModMissingException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Services\ModListService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Add-to-list modal. The `source` property identifies whether the modal was
 * triggered from a mod or addon detail page.
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

    public ?string $flashMessage = null;

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

        $rows = $user->modLists()
            ->withCount('items')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        /** @var Collection<int, ModList> $lists */
        $lists = new Collection;
        foreach ($rows as $row) {
            $lists->push($row);
        }

        return $lists;
    }

    /**
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function suggestedDependencies(): Collection
    {
        if (! $this->activeListId || $this->sourceType !== 'mod') {
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

    public function addToList(int $listId, ModListService $service): void
    {
        $this->flashMessage = null;

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
                $ids = [];
                foreach ($suggested as $dep) {
                    $ids[] = (int) $dep->id;
                }

                $this->selectedDependencyIds = $ids;
                $this->showDependencyStep = true;

                return;
            }

            $deps = $suggested->filter(fn (Mod $m): bool => in_array($m->id, $this->selectedDependencyIds, true));

            Gate::authorize('addItem', $list);

            try {
                $service->addMod($list, $mod, $this->note === '' ? null : $this->note, $deps);
                $this->flashMessage = __('Added to ":title".', ['title' => $list->title]);
                $this->resetSubFlow();
            } catch (ModListCapacityExceededException $e) {
                $this->flashMessage = $e->getMessage();
            }

            return;
        }

        // Addon flow
        $addon = Addon::query()->findOrFail($this->sourceId);

        Gate::authorize('addItem', $list);

        try {
            $service->addAddon($list, $addon, $this->note === '' ? null : $this->note, includeParentMod: true);
            $this->flashMessage = __('Added to ":title".', ['title' => $list->title]);
        } catch (ParentModMissingException|ModListCapacityExceededException $e) {
            $this->flashMessage = $e->getMessage();
        }
    }

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
            $this->flashMessage = __('Removed from ":title".', ['title' => $list->title]);
        }
    }

    public function confirmDependencies(ModListService $service): void
    {
        if ($this->activeListId === null) {
            return;
        }

        $this->addToList($this->activeListId, $service);
    }

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
    }
};
