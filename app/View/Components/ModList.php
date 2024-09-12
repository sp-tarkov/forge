<?php

namespace App\View\Components;

use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class ModList extends Component
{
    /**
     * The mods to display.
     *
     * @var Collection<int, Mod>
     */
    public Collection $mods;

    public string $versionScope;

    /**
     * Create a new component instance.
     *
     * @param  Collection<int, Mod>  $mods
     */
    public function __construct(Collection $mods, string $versionScope)
    {
        $this->mods = $mods;
        $this->versionScope = $versionScope;
    }

    public function render(): View
    {
        return view('components.mod-list', [
            'mods' => $this->mods,
            'versionScope' => $this->versionScope,
        ]);
    }
}
