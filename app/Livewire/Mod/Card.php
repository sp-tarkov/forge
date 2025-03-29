<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Card extends Component
{
    /**
     * The mod instance.
     */
    #[Locked]
    public Mod $mod;

    /**
     * The mod version instance. Passed dynamically as the component is used in different contexts; sometimes
     * to display the mod's latest version, or sometimes the last updated version.
     */
    #[Locked]
    public ModVersion $version;

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
     * Features the mod.
     */
    public function feature(): void
    {
        $this->authorize('feature', $this->mod);

        $this->mod->featured = true;
        $this->mod->save();

        $this->confirmModFeature = false;

        flash()->success('Mod successfully featured!');
    }

    /**
     * Unfeatures the mod.
     *
     * The homepage component should be used to process unfeature actions outside the context of the homepage featured
     * section so the homepage listing can be updated.
     */
    public function unfeature(): void
    {
        $this->authorize('unfeature', $this->mod);

        $this->mod->featured = false;
        $this->mod->save();

        $this->confirmModUnfeature = false;

        flash()->success('Mod successfully unfeatured!');
    }

    /**
     * Handles UI changes of the unfeature actions in both the Card and Homepage contexts.
     */
    public function postUnfeature(): void
    {
        $this->confirmModUnfeature = false;

        flash()->success('Mod successfully unfeatured!');
    }

    /**
     * Disables the mod.
     */
    public function disable(): void
    {
        $this->authorize('disable', $this->mod);

        $this->mod->disabled = true;
        $this->mod->save();

        $this->confirmModDisable = false;

        flash()->success('Mod successfully disabled!');
    }

    /**
     * Enables the mod.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->mod);

        $this->mod->disabled = false;
        $this->mod->save();

        $this->confirmModEnable = false;

        flash()->success('Mod successfully enabled!');
    }

    /**
     * Render the component view.
     */
    public function render(): View|string
    {
        return view('livewire.mod.card');
    }
}
