<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Moderation extends Component
{
    /**
     * The mod being moderated.
     */
    #[Locked]
    public Mod $mod;

    /**
     * The current route that this component is being rendered on.
     */
    #[Locked]
    public string $currentRoute = '';

    /**
     * The state of the confirmation dialog for deleting the mod.
     */
    public bool $confirmModDelete = false;

    /**
     * The state of the confirmation dialog for deleting the mod.
     */
    public bool $confirmModDisable = false;

    /**
     * The state of the confirmation dialog for deleting the mod.
     */
    public bool $confirmModEnable = false;

    /**
     * The state of the confirmation dialog for featuring the mod.
     */
    public bool $confirmModFeature = false;

    /**
     * The state of the confirmation dialog for unfeaturing the mod.
     */
    public bool $confirmModUnfeature = false;

    /**
     * Deletes the mod.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->mod);

        $this->mod->delete();

        $this->confirmModDelete = false;

        flash()->success('Mod successfully deleted!');

        $this->dispatch('mod-delete', $this->mod->id);

        if ($this->currentRoute === 'mod.show') {
            // On a mod show page, we can redirect to the mods listing page as the mod no longer exists.
            $this->redirectRoute('mods');
        }
    }

    /**
     * Features the mod.
     */
    public function feature(): void
    {
        $this->authorize('feature', $this->mod);

        $this->mod->featured = true;
        $this->mod->save();

        $this->mod->refresh();

        $this->confirmModFeature = false;

        flash()->success('Mod successfully featured!');

        $this->emitUpdateEvent();
    }

    /**
     * Features the mod.
     */
    public function unfeature(): void
    {
        $this->authorize('unfeature', $this->mod);

        $this->mod->featured = false;
        $this->mod->save();

        $this->mod->refresh();

        $this->confirmModUnfeature = false;

        flash()->success('Mod successfully unfeatured!');

        $this->emitUpdateEvent();
    }

    /**
     * Disables the mod.
     */
    public function disable(): void
    {
        $this->authorize('disable', $this->mod);

        $this->mod->disabled = true;
        $this->mod->save();

        $this->mod->refresh();

        $this->confirmModDisable = false;

        flash()->success('Mod successfully disabled!');

        $this->emitUpdateEvent();
    }

    /**
     * Enables the mod.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->mod);

        $this->mod->disabled = false;
        $this->mod->save();

        $this->mod->refresh();

        $this->confirmModEnable = false;

        flash()->success('Mod successfully enabled!');

        $this->emitUpdateEvent();
    }

    /**
     * Emit an event that the mod has been updated.
     */
    protected function emitUpdateEvent(): void
    {
        $this->dispatch('mod-updated.'.$this->mod->id, $this->mod->disabled, $this->mod->featured);
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.moderation');
    }
}
