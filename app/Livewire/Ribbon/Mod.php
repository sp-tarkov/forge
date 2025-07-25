<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Mod as ModModel;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Mod extends Component
{
    /**
     * The mod model.
     */
    public ModModel $mod;

    /**
     * Whether the ribbon is the home page.
     */
    #[Locked]
    public bool $homepageFeatured = false;

    /**
     * Refresh the mod model when it's updated.
     */
    #[On('mod-updated.{mod.id}')]
    public function refreshMod(): void
    {
        $this->mod->refresh();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $ribbonData = null;
        if ($this->mod->disabled) {
            $ribbonData = ['color' => 'red', 'label' => __('Disabled')];
        } elseif ($this->mod->published_at === null) {
            $ribbonData = ['color' => 'amber', 'label' => __('Unpublished')];
        } elseif ($this->mod->published_at > now()) {
            $ribbonData = ['color' => 'emerald', 'label' => __('Scheduled')];
        } elseif ($this->mod->featured && ! $this->homepageFeatured) {
            $ribbonData = ['color' => 'sky', 'label' => __('Featured!')];
        }

        return view('livewire.ribbon.mod', compact('ribbonData'));
    }
}
