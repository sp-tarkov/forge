<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Carbon;
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
     * The mod published date.
     */
    public ?Carbon $publishedAt = null;

    /**
     * Whether the mod is featured Defaults to false.
     */
    public bool $featured = false;

    /**
     * Whether the ribbon is the home page.
     */
    public bool $homepageFeatured = false;

    /**
     * Triggered when the model has been updated.
     */
    #[On('mod-updated.{id}')]
    #[On('mod-version-updated.{id}')]
    public function update(?bool $disabled = null, ?bool $featured = null, ?Carbon $publishedAt = null): void
    {
        if ($disabled !== null) {
            $this->disabled = $disabled;
        }

        if ($publishedAt !== null) {
            $this->publishedAt = $publishedAt;
        }

        if ($featured !== null) {
            $this->featured = $featured;
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.ribbon');
    }
}
