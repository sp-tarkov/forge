<?php

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
     * Mount the component.
     */
    public function mount(Mod $mod): void
    {
        $this->mod = $mod;
    }

    /**
     * Soft delete the mod.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->mod);

        defer(fn () => $this->mod->delete());

        flash()->success('Mod deleted successfully!');

        // Fire an event so that the listings can be updated.
        $this->dispatch('mod-delete', $this->mod->id);

        if ($this->currentRoute === 'mod.show') {
            // On a mod show page, we can redirect to the mods listing page as the mod no longer exists.
            $this->redirectRoute('mods');
        }
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.moderation');
    }
}
