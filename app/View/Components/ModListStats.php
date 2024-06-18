<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ModListStats extends Component
{
    public function __construct(
        public $mod,
        public $modVersion
    ) {
    }

    public function render(): View
    {
        return view('components.mod-list-stats');
    }
}
