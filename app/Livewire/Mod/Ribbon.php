<?php

namespace App\Livewire\Mod;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Ribbon extends Component
{
    /**
     * The ID of the object that the ribbon is applied to.
     */
    public int $id;

    /**
     * Whether the object is disabled.
     */
    public bool $disabled;

    /**
     * Whether the object is featured (typically used for mods). Defaults to false.
     */
    public bool $featured = false;

    /**
     * Triggered when the object has had its relevant properties updated and the ribbon needs to reflect the changes.
     */
    #[On('updateProperties')]
    public function handleUpdateProperties(int $targetId, bool $newDisabled, bool $newFeatured): void
    {
        if ($this->id === $targetId) {
            $this->disabled = $newDisabled;
            $this->featured = $newFeatured;
        }
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.ribbon');
    }
}
