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
     * The mod instance.
     */
    #[Locked]
    public Mod $mod;

    /**
     * The route name of the current page on initialization of the component.
     */
    #[Locked]
    public string $routeName = '';

    /**
     * Is this mod card is in the featured section of the homepage. Changing the featured state in this context requires
     * processing the action through the homepage component so the listing can be updated.
     */
    #[Locked]
    public bool $homepageFeatured = false;

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
     * The state of the confirmation dialog for deleting the mod.
     */
    public bool $confirmModDelete = false;

    /**
     * Executed when the component is first loaded.
     */
    public function mount(): void
    {
        $this->routeName = request()->route()?->getName() ?? '';
    }

    /**
     * Features the mod.
     */
    public function feature(): void
    {
        $this->confirmModFeature = false;

        $this->authorize('feature', $this->mod);

        $this->mod->featured = true;
        $this->mod->save();

        $this->dispatch('mod-updated.'.$this->mod->id, featured: true); // Ribbon update.

        flash()->success('Mod successfully featured!');
    }

    /**
     * Unfeatures the mod.
     */
    public function unfeature(): void
    {
        $this->confirmModUnfeature = false;

        $this->authorize('unfeature', $this->mod);

        $this->mod->featured = false;
        $this->mod->save();

        $this->dispatch('mod-updated.'.$this->mod->id, featured: false); // Ribbon update.

        flash()->success('Mod successfully unfeatured!');
    }

    /**
     * Disables the mod.
     */
    public function disable(): void
    {
        $this->confirmModDisable = false;

        $this->authorize('disable', $this->mod);

        $this->mod->disabled = true;
        $this->mod->save();

        $this->dispatch('mod-updated.'.$this->mod->id, disabled: true); // Ribbon update.

        flash()->success('Mod successfully disabled!');
    }

    /**
     * Enables the mod.
     */
    public function enable(): void
    {
        $this->confirmModEnable = false;

        $this->authorize('enable', $this->mod);

        $this->mod->disabled = false;
        $this->mod->save();

        $this->dispatch('mod-updated.'.$this->mod->id, disabled: false); // Ribbon update.

        flash()->success('Mod successfully enabled!');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.mod.moderation');
    }
}
