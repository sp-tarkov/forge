<?php

namespace App\Livewire\Page\ModAddon;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.base')]
class Show extends Component
{
    public function render()
    {
        return view('livewire.page.mod-addon.show');
    }
}
