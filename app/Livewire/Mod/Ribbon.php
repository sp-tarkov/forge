<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Ribbon extends Component
{
    /**
     * The ID of the model that the ribbon is applied to.
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
     * Triggered when the model has been updated.
     */
    #[On('mod-updated.{id}')]
    #[On('mod-version-updated.{id}')]
    public function handleUpdateProperties(bool $newDisabled, ?bool $newFeatured = null): void
    {
        $this->disabled = $newDisabled;
        if ($newFeatured !== null) {
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
