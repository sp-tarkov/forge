<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Rules\Semver;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateModForm extends Component
{
    #[Validate('required|string|max:255')]
    public $modName = '';

    public $modAvatar;

    #[Validate(['required', 'string', new Semver])]
    public $modVersion = '';

    #[Validate('string|max:255')]
    public $modTeaser = '';

    #[Validate('string')]
    public $modDescription = '';

    //public $modExternalUrl = '';
    //public $modVirusTotalUrl = '';

    //public \DateTime $publishDate;

    public function save()
    {
        $validated = $this->validate();

        if ($validated) {
            // TODO: actually save data here

            flash()->success("Mod '$this->modName' Created");
        }
    }

    public function render()
    {
        return view('livewire.page.mod.create-mod-form');
    }
}
