<?php

declare(strict_types=1);

namespace App\Livewire\Page\Addon;

use App\Models\Addon;
use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class GuidelinesAcknowledgment extends Component
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

        $this->redirect(route('addon.create', ['mod' => $this->mod->id]));
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.addon.guidelines-acknowledgment');
    }
}
