<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use Livewire\Component;

class CreateModForm extends Component
{
    public $modName = '';
    public $modVersion = '';
    public $modTeaser = '';
    public $modDescription = '';
    public $modExternalUrl = '';
    public $modCategory = '';
    public $modIcon = '';
    public \DateTime $publishDate;

    public function save()
    {
        // TODO: this lol
        flash()->success("Mod '$this->modName' Created. Publishing at {$this->publishDate->format('Y-m-d')}");
    }

    public function render()
    {
        return view('livewire.page.mod.create-mod-form');
    }
}
