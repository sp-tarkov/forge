<?php

namespace App\Livewire\Mod;

use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Component;

class DownloadButton extends Component
{
    /**
     * The mod to show the download button for.
     */
    public Mod $mod;

    /**
     * Whether to show latest version download model dialog.
     */
    public bool $showDownloadDialog = false;

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.download-button');
    }

    /**
     * Toggle the download dialog.
     */
    public function toggleDownloadDialog(): void
    {
        $this->showDownloadDialog = ! $this->showDownloadDialog;
    }
}
