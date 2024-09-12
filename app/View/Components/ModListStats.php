<?php

namespace App\View\Components;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ModListStats extends Component
{
    public function __construct(
        public Mod $mod,
        public ModVersion $modVersion
    ) {}

    public function render(): View
    {
        return view('components.mod-list-stats');
    }
}
