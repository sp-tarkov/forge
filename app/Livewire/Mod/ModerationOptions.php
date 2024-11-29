<?php

namespace App\Livewire\Mod;

use App\Models\ModeratedModel;
use Livewire\Component;

class ModerationOptions extends Component
{
    public ModeratedModel $moderatedObject;

    public function render()
    {
        return view('livewire.mod.moderation-options');
    }

    public function delete(): void
    {
        $this->moderatedObject->delete();
        $this->js('window.location.reload()');
    }

    public function toggleDisabled(): void
    {
        $this->moderatedObject->toggleDisabled();
        $this->js('window.location.reload()');
    }
}
