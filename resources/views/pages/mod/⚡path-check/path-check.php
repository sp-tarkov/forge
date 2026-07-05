<?php

declare(strict_types=1);

use App\Models\Mod;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Mod::class);
    }

    /**
     * User confirms a mod is the right path and proceeds to mod creation.
     */
    public function proceed(): void
    {
        $this->authorize('create', Mod::class);

        $this->redirect(route('mod.create'));
    }
};
