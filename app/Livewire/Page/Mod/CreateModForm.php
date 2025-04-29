<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Models\SptVersion;
use App\Rules\SemverConstraint;
use Composer\Semver\Semver;
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
    public $modAvatar;

    #[Validate(['required', 'string', new \App\Rules\Semver])]
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
    public $modPublishDate;

    /**
     * The matching SPT versions for the current constraint.
     *
     * @var array<int, array{version: string, color_class: string}>
     */
    public array $matchingSptVersions = [];

    /**
     * Update the matching SPT versions when the constraint changes.
     */
    public function updatedModSptVersionConstraint(): void
    {
        if (empty($this->modSptVersionConstraint)) {
            $this->matchingSptVersions = [];

            return;
        }

        try {
            $validSptVersions = SptVersion::allValidVersions();
            $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $this->modSptVersionConstraint);

            $this->matchingSptVersions = SptVersion::query()
                ->whereIn('version', $compatibleSptVersions)
                ->select(['version', 'color_class'])
                ->orderByDesc('version_major')
                ->orderByDesc('version_minor')
                ->orderByDesc('version_patch')
                ->orderBy('version_labels')
                ->get()
                ->map(fn (SptVersion $version): array => [
                    'version' => $version->version,
                    'color_class' => $version->color_class,
                ])
                ->toArray();
        } catch (Exception) {
            $this->matchingSptVersions = [];
        }
    }

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
