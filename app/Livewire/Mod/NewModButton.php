<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use Livewire\Component;

class NewModButton extends Component
{
    public function newMod(): void
    {
        $this->redirectRoute('mod.create');
    }

    public function render()
    {
        return view('livewire.mod.new-mod-button');
    }
}
