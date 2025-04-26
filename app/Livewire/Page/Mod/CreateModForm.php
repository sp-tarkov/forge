<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Rules\Semver;
use App\Rules\SemverConstraint;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateModForm extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public $modName = '';

    #[Validate('nullable|image|mimes:jpg,jpeg,png|max:2048')] // 2MB Max
    public $modAvatar = null;

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

    #[Validate('nullable|date')]
    public $modPublishDate = null;

    public function save()
    {
        Log::info('Creating mod');

        $validated = $this->validate();

        if ($validated) {
            try {
                DB::beginTransaction();

                $mod = Mod::query()->create([
                    'owner_id' => auth()->id(),
                    'name' => $this->modName,
                    'slug' => Str::slug($this->modName),
                    'teaser' => $this->modTeaser,
                    'description' => $this->modDescription,
                    'source_code_url' => $this->modSourceCodeUrl,
                ]);

                if ($this->modPublishDate) {
                    $mod->published_at = $this->modPublishDate;
                }

                $modVersion = $mod->versions()->create([
                    'version' => $this->modVersion,
                    'description' => $this->modDescription,
                    'virus_total_link' => $this->modVirusTotalUrl,
                    'spt_version_constraint' => $this->modSptVersionConstraint,
                    'link' => $this->modExternalUrl,
                ]);

                // I think this is how to make avatar optional?
                if ($this->modAvatar) {
                    $mod->thumbnail = $this->modAvatar->storePublicly(
                        path: 'mods',
                        options: config('filesystems.asset_upload_disk', 'public'),
                    );
                }

                $mod->save();

                DB::commit();

                Log::info(json_encode($mod));

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
