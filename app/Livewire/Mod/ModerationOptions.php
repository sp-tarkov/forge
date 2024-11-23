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
        $this->js('window.location.reload()');
    }

    public function toggleDisabled(): void
    {
        $this->mod->disabled = ! $this->mod->disabled;
        $this->mod->save();
        $this->js('window.location.reload()');
    }
}
