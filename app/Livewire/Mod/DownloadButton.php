<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DownloadButton extends Component
{
    /**
     * The mod to show the download button for.
     */
    #[Locked]
    public Mod $mod;

    /**
     * CSS classes that are applied to the component.
     */
    public string $classes = '';

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
}
