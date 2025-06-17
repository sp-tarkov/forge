<?php

declare(strict_types=1);

namespace App\Livewire\Page\ModAddon;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.base')]
class Edit extends Component
{
    public function render()
    {
        return view('livewire.page.mod-addon.edit');
    }
}
