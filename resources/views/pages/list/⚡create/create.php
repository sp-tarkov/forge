<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Livewire\Forms\ModListForm;
use App\Models\ModList;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    public ModListForm $form;

    public function mount(): void
    {
        Gate::authorize('create', ModList::class);

        $this->form->visibility = ListVisibility::Private->value;
    }

    public function save(): void
    {
        Gate::authorize('create', ModList::class);

        $list = $this->form->save();

        $this->redirectRoute('list.show', [
            'listId' => $list->id,
            'slug' => $list->slug,
        ]);
    }
};
