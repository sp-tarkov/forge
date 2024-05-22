<?php

namespace App\View\Components;

use App\Helpers\ColorHelper;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ModList extends Component
{
    public $mods;

    public string $versionScope;

    public function __construct($mods, $versionScope)
    {
        $this->mods = $mods;
        $this->versionScope = $versionScope;

        foreach ($this->mods as $mod) {
            $color = $mod->{$this->versionScope}->sptVersion->color_class;
            $mod->colorClass = ColorHelper::tagColorClasses($color);
        }
    }

    public function render(): View
    {
        return view('components.mod-list', [
            'mods' => $this->mods,
            'versionScope' => $this->versionScope,
        ]);
    }
}
