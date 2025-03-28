<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\ModVersion;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Version extends Component
{
    /**
     * The Mod Version to be displayed.
     */
    #[Locked]
    public ModVersion $version;

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.version');
    }
}
