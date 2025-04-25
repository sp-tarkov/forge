<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Rules\Semver;
use App\Rules\SemverConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateModForm extends Component
{
    #[Validate('required|string|max:255')]
    public $modName = '';

    public $modAvatar;

    #[Validate(['required', 'string', new Semver])]
    public $modVersion = '';

    #[Validate(['required', 'string', new SemverConstraint])]
    public $modSptVersionConstraint = '';

    #[Validate('string|max:255')]
    public $modTeaser = '';

    #[Validate('string')] // should this have a max length?
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
            try {
                DB::beginTransaction();

                $mod = Mod::query()->create([
                    'name' => $this->modName,
                    'slug' => Str::slug($this->modName),
                    'description' => $this->modDescription,
                    'teaser' => $this->modTeaser,
                    'source_code_url' => $this->modSourceCodeUrl,
                ]);

                $modVersion = $mod->versions()->create([
                    'version' => $this->modVersion,
                    'description' => $this->modDescription,
                    'virus_total_link' => $this->modVirusTotalUrl,
                    'spt_version_constraint' => $this->modSptVersionConstraint,
                    'link' => $this->modExternalUrl,
                    'downloads' => 0,
                ]);

                DB::commit();

                flash()->success(sprintf("Mod '%s' Created", $this->modName));
                $this->redirect($mod->detail_url);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error creating mod/version: '.$e->getMessage());

                flash()->error('Error creating mod/version: '.$e->getMessage());
            }
        }
    }

    public function cancel()
    {
        $this->redirectRoute('mods');
    }

    public function render()
    {
        return view('livewire.page.mod.create-mod-form');
    }
}
