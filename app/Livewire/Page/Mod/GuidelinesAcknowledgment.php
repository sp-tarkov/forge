<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class GuidelinesAcknowledgment extends Component
{
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

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod.guidelines-acknowledgment');
    }
}
