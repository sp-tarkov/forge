<?php

namespace App\Livewire\Mod;

use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ModerationOptions extends Component
{
    public function render()
    {
        return view('livewire.mod.moderation-options');
    }

    public function deleteMod(): void
    {
        Log::info('delete');
    }

    public function disableMod(): void
    {
        Log::info('disable');
    }
}
