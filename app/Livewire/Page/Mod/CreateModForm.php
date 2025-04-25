<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
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

    #[Validate('string')]  // should this have a max length?
    public $modDescription = '';

    #[Validate('required|url')]
    public $modExternalUrl = '';

    #[Validate('required|url|starts_with:https://www.virustotal.com/gui/file')]
    public $modVirusTotalUrl = '';

    #[Validate('required|url')]
    public $modSourceCodeUrl = '';

    // public \DateTime $publishDate;

    public function save()
    {
        $validated = $this->validate();

        if ($validated) {


            $mod = new Mod();
            $newVersion = new ModVersion();

            $newVersion->version = $this->modVersion;
            $newVersion->description = $this->modDescription;
            $newVersion->virus_total_link = $this->modVirusTotalUrl;
            $newVersion->link = $this->modExternalUrl;

            $mod->name = $this->modName;
            $mod->slug = str_replace(' ', '-', strtolower($this->modName));
            $mod->description = $this->modDescription;
            $mod->teaser = $this->modTeaser;
            $mod->source_code_url = $this->modSourceCodeUrl;

            $mod->save();
            $mod->versions()->save($newVersion);

            flash()->success("Mod '$this->modName' Created");
            $this->redirect("mod/$mod->id/$mod->slug");
        }
    }

    public function cancel() {
        $this->redirectRoute('mods');
    }

    public function render()
    {
        return view('livewire.page.mod.create-mod-form');
    }
}
