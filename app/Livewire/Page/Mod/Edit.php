<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class Edit extends Component
{
    use UsesSpamProtection;
    use WithFileUploads;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The mod being edited.
     */
    public Mod $mod;

    /**
     * The avatar of the mod.
     */
    #[Validate('nullable|image|mimes:jpg,jpeg,png|max:2048')]
    public ?UploadedFile $avatar = null;

    /**
     * The name of the mod.
     */
    #[Validate('required|string|max:75')]
    public string $name = '';

    /**
     * The teaser of the mod.
     */
    #[Validate('required|string|max:255')]
    public string $teaser = '';

    /**
     * The description of the mod.
     */
    #[Validate('required|string')]
    public string $description = '';

    /**
     * The license of the mod.
     */
    #[Validate('required|exists:licenses,id')]
    public string $license = '';

    /**
     * The source code URL of the mod.
     */
    #[Validate('required|url|starts_with:https://,http://')]
    public string $sourceCodeUrl = '';

    /**
     * The published at date of the mod.
     */
    #[Validate('nullable|date')]
    public ?string $publishedAt = null;

    /**
     * Whether the mod contains AI content.
     */
    #[Validate('boolean')]
    public bool $containsAiContent = false;

    /**
     * Whether the mod contains ads.
     */
    #[Validate('boolean')]
    public bool $containsAds = false;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->honeypotData = new HoneypotData;

        $this->authorize('update', $this->mod);

        $this->mod = Mod::query()->findOrFail($modId);

        // Prefill fields from the mod
        $this->name = $this->mod->name;
        $this->teaser = $this->mod->teaser;
        $this->description = $this->mod->description;
        $this->license = (string) $this->mod->license_id;
        $this->sourceCodeUrl = $this->mod->source_code_url;
        $this->publishedAt = $this->mod->published_at ? Carbon::parse($this->mod->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->toDateTimeString() : null;
        $this->containsAiContent = (bool) $this->mod->contains_ai_content;
        $this->containsAds = (bool) $this->mod->contains_ads;
    }

    /**
     * Save the mod.
     */
    public function save(): void
    {
        $this->authorize('update', $this->mod);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Carbon::parse($this->publishedAt, $userTimezone)->setTimezone('UTC');
        }

        // Update mod fields
        $this->mod->name = $this->name;
        $this->mod->slug = Str::slug($this->name);
        $this->mod->teaser = $this->teaser;
        $this->mod->description = $this->description;
        $this->mod->license_id = (int) $this->license;
        $this->mod->source_code_url = $this->sourceCodeUrl;
        $this->mod->contains_ai_content = $this->containsAiContent;
        $this->mod->contains_ads = $this->containsAds;
        $this->mod->published_at = $publishedAtCarbon;

        // Set the thumbnail if an avatar was uploaded.
        if ($this->avatar !== null) {
            $this->mod->thumbnail = $this->avatar->storePublicly(
                path: 'mods',
                options: config('filesystems.asset_upload', 'public'),
            );
        }

        $this->mod->save();

        flash()->success('Mod has been Successfully Updated');

        $this->redirect($this->mod->detail_url);
    }

    /**
     * Remove the uploaded avatar.
     */
    public function removeAvatar(): void
    {
        $this->avatar = null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.page.mod.edit', [
            'mod' => $this->mod,
        ]);
    }
}
