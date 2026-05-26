<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\User;
use App\Services\ModListService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /**
     * The id of the source list being forked or duplicated.
     */
    #[Locked]
    public int $sourceId;

    /**
     * Whether the viewer is allowed to fork the source. Controls visibility of the trigger button so the rest of the
     * page can render it unconditionally.
     */
    #[Locked]
    public bool $canFork = false;

    /**
     * Whether the source list is owned by the current viewer. Drives the user-facing label ("Duplicate" vs "Fork").
     */
    #[Locked]
    public bool $isOwnList = false;

    /**
     * Title pre-filled in the modal form, seeded from the source list's title.
     */
    public string $title = '';

    /**
     * Backs the Flux modal open state via wire:model.self.
     */
    public bool $showForkModal = false;

    public function mount(int $sourceId): void
    {
        $this->sourceId = $sourceId;

        $source = ModList::query()->find($sourceId);
        if (! $source instanceof ModList) {
            return;
        }

        $this->canFork = Gate::allows('fork', $source);
        $this->isOwnList = Auth::id() === $source->owner_id;
        $this->title = $source->title;
    }

    public function submit(ModListService $service): void
    {
        $source = ModList::query()->find($this->sourceId);
        abort_unless($source instanceof ModList, 404);

        Gate::authorize('fork', $source);

        $this->validate([
            'title' => [
                'required',
                'string',
                'min:1',
                'max:'.config()->integer('mod-lists.validation.title_max', 120),
            ],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $list = $service->forkList($user, $source, $this->title);

        Flux::toast(
            heading: $this->isOwnList ? __('List duplicated') : __('List forked'),
            text: $this->isOwnList
                ? __('A copy of the list has been created in your account.')
                : __('A fork of the list has been created in your account.'),
            variant: 'success',
        );

        $this->redirectRoute('list.show', [
            'listId' => $list->id,
            'slug' => $list->slug,
        ]);
    }
};
