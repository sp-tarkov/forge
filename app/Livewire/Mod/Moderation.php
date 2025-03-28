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
     * Soft delete the mod.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->mod);

        $this->mod->delete();

        $this->modal('moderation-mod-delete-'.$this->mod->id)->close();

        flash()->success('Mod successfully deleted!');

        $this->dispatch('mod-delete', $this->mod->id);

        if ($this->currentRoute === 'mod.show') {
            // On a mod show page, we can redirect to the mods listing page as the mod no longer exists.
            $this->redirectRoute('mods');
        }
    }

    /**
     * Toggles the disabled property of the mod.
     */
    public function toggleDisabled(): void
    {
        $this->authorize('update', $this->mod);

        $this->mod->disabled = ! $this->mod->disabled;
        $this->mod->save();
        $this->mod->refresh();

        $this->modal('moderation-mod-disable-'.$this->mod->id)->close();

        flash()->success('Mod successfully disabled!');

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
