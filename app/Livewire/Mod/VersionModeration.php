<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\ModVersion;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class VersionModeration extends Component
{
    /**
     * The mod being moderated.
     */
    #[Locked]
    public ModVersion $version;

    /**
     * The state of the confirmation dialog for deleting the mod version.
     */
    public bool $confirmVersionDelete = false;

    /**
     * The state of the confirmation dialog for deleting the mod version.
     */
    public bool $confirmVersionDisable = false;

    /**
     * The state of the confirmation dialog for deleting the mod version.
     */
    public bool $confirmVersionEnable = false;

    /**
     * Disables the version.
     */
    public function disable(): void
    {
        $this->confirmVersionDisable = false;

        $this->authorize('disable', $this->version);

        $this->version->disabled = true;
        $this->version->save();

        flash()->success('Mod version successfully disabled!');

        $this->dispatch('mod-version-updated.'.$this->version->id, disabled: true); // Ribbon update.
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $this->confirmVersionEnable = false;

        $this->authorize('enable', $this->version);

        $this->version->disabled = false;
        $this->version->save();

        flash()->success('Mod version successfully enabled!');

        $this->dispatch('mod-version-updated.'.$this->version->id, disabled: false); // Ribbon update.
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.version-moderation');
    }
}
