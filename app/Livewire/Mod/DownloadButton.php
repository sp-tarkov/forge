<?php

namespace App\Livewire\Mod;

use App\Models\Mod;
use Livewire\Component;

class DownloadButton extends Component
{
    public Mod $mod;

    public string $classes = "";

    /**
     * Whether to show latest version download model dialog.
     */
    public bool $showDownloadDialog = false;

    public function render()
    {
        return view('livewire.mod.download-button');
    }

    public function toggleDownloadDialog(): void
    {
        $this->showDownloadDialog = !$this->showDownloadDialog;
    }
}
