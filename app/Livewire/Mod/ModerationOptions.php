<?php

namespace App\Livewire\Mod;

use App\Models\Mod;
use Livewire\Component;

class ModerationOptions extends Component
{
    public Mod $mod;

    public function render()
    {
        return view('livewire.mod.moderation-options');
    }

    public function deleteMod(): void
    {
        $this->mod->delete();
    }

    public function disableMod(): void
    {
        $this->mod->disabled = true;
        $this->mod->save();
    }
}
