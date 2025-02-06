<?php

namespace App\Livewire\Mod;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View as ContractView;
use Illuminate\Foundation\Application;
use Illuminate\View\View;
use Livewire\Component;

class ModerationOptions extends Component
{
    public string $objectId;

    public string $targetType;

    public bool $disabled;

    public string $displayName;

    public bool $showDeleteDialog = false;

    public bool $showDisableDialog = false;

    public function render(): Application|Factory|ContractView|View
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
