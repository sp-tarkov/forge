<?php

namespace App\Livewire\Mod;

use Livewire\Component;

class ModerationOptions extends Component
{
    public string $objectId;

    public string $targetType;

    public bool $disabled;

    public string $displayName;

    public bool $showDeleteDialog = false;

    public bool $showDisableDialog = false;

    public function render()
    {
        return view('livewire.mod.moderation-options');
    }

    public function confirmDelete(): void
    {
        $this->showDeleteDialog = true;
    }

    public function confirmDisable(): void
    {
        $this->showDisableDialog = true;
    }
}
