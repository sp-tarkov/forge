<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    /**
     * The mod that the addon will be created for.
     */
    public Mod $mod;

    /**
     * Mount the component.
     */
    public function mount(Mod $mod): void
    {
        $this->mod = $mod;

        $this->authorize('create', [Addon::class, $this->mod]);
    }

    /**
     * User agrees to guidelines and proceeds to addon creation.
     */
    public function agree(): void
    {
        $this->authorize('create', [Addon::class, $this->mod]);

        $this->redirect(route('addon.path-check', ['mod' => $this->mod->id]));
    }
};
