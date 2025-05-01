<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    #[Validate('nullable|image|mimes:jpg,jpeg,png|max:2048')]
    public ?UploadedFile $avatar = null;

    #[Validate('required|string|max:75')]
    public string $name = '';

    #[Validate('required|string|max:255')]
    public string $teaser = '';

    #[Validate('required|string')]
    public string $description = '';

    #[Validate('required|exists:licenses,id')]
    public string $license = '';

    #[Validate('required|url|starts_with:https://,http://')]
    public string $sourceCodeUrl = '';

    #[Validate('boolean')]
    public bool $containsAiContent = false;

    #[Validate('boolean')]
    public bool $containsAds = false;

    public function save(): void
    {
        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Create a new mod instance.
        $mod = new Mod([
            'owner_id' => auth()->id(),
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'source_code_url' => $this->sourceCodeUrl,
            'contains_ai_content' => $this->containsAiContent,
            'contains_ads' => $this->containsAds,
        ]);

        // Set the thumbnail if an avatar was uploaded.
        if ($this->avatar) {
            $mod->thumbnail = $this->avatar->storePublicly(
                path: 'mods',
                options: config('filesystems.asset_upload', 'public'),
            );
        }

        // Save the mod.
        $mod->save();

        flash()->success(sprintf("Mod '%s' Successfully Created", $this->name));

        $this->redirect($mod->detail_url);
    }

    public function render(): View
    {
        return view('livewire.page.mod.create');
    }
}
