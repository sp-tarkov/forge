<?php

declare(strict_types=1);

use App\Models\Mod;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Mod::class);
    }

    /**
     * User agrees to guidelines and proceeds to mod creation.
     */
    public function agree(): void
    {
        $this->authorize('create', Mod::class);

        $this->redirect(route('mod.create'));
    }
};
