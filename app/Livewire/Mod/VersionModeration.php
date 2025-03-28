<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
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
     * Deletes the version.
     *
     * TODO: While this *does* delete the version, the listing doesn't react as the versions are pulled outside of
     *       livewire. I want to migrate to using full page livewire components to help with this.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->version);

        $this->version->delete();

        $this->confirmVersionDelete = false;

        flash()->success('Mod version successfully deleted!');

        // $this->dispatch('mod-version-delete', $this->version->id);
    }

    /**
     * Disables the version.
     */
    public function disable(): void
    {
        $this->authorize('disable', $this->version);

        $this->version->disabled = true;
        $this->version->save();

        $this->version->refresh();

        $this->confirmVersionDisable = false;

        flash()->success('Mod version successfully disabled!');

        $this->emitUpdateEvent();
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->version);

        $this->version->disabled = false;
        $this->version->save();

        $this->version->refresh();

        $this->confirmVersionEnable = false;

        flash()->success('Mod version successfully enabled!');

        $this->emitUpdateEvent();
    }

    /**
     * Emit an event for the version being updated.
     */
    protected function emitUpdateEvent(): void
    {
        $this->dispatch('mod-version-updated.'.$this->version->id, $this->version->disabled);
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.version-moderation');
    }
}
