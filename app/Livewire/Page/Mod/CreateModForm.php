<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use Livewire\Component;

class CreateModForm extends Component
{
    // placeholder
    public $title = '';

    // placeholder
    public $content = '';

    public function save()
    {
        // TODO: this lol
        flash()->success('save test');
    }

    public function render()
    {
        return view('livewire.page.mod.create-mod-form');
    }
}
