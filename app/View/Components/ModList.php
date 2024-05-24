<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class ModList extends Component
{
    public Collection $mods;

    public string $versionScope;

    public function __construct($mods, $versionScope)
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
