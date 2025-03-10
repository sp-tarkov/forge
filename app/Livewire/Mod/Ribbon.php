<?php

namespace App\Livewire\Mod;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Ribbon extends Component
{
    /**
     * The ID of the mod that the ribbon is applied to.
     */
    public int $id;

    /**
     * Whether the mod is disabled.
     */
    public bool $disabled;

    /**
     * Whether the mod is featured Defaults to false.
     */
    public bool $featured = false;

    /**
     * Whether the ribbon is the home page.
     */
    public bool $isHomePage = false;

    /**
     * Triggered when the mod has had its relevant properties updated and the ribbon needs to reflect the changes.
     */
    #[On('mod-updated.{id}')]
    public function handleUpdateProperties(bool $newDisabled, bool $newFeatured): void
    {
        $this->disabled = $newDisabled;
        $this->featured = $newFeatured;
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.ribbon');
    }
}
