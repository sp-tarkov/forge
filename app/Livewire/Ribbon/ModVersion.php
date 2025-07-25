<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\ModVersion as ModVersionModel;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ModVersion extends Component
{
    /**
     * The mod version model.
     */
    public ModVersionModel $version;

    /**
     * Refresh the mod version model when it's updated.
     */
    #[On('mod-version-updated.{version.id}')]
    public function refreshVersion(): void
    {
        $this->version->refresh();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $ribbonData = null;
        if ($this->version->disabled) {
            $ribbonData = ['color' => 'red', 'label' => __('Disabled')];
        } elseif ($this->version->published_at === null) {
            $ribbonData = ['color' => 'amber', 'label' => __('Unpublished')];
        } elseif ($this->version->published_at > now()) {
            $ribbonData = ['color' => 'emerald', 'label' => __('Scheduled')];
        }

        return view('livewire.ribbon.mod-version', compact('ribbonData'));
    }
}
