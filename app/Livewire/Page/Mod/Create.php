<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;

class Create extends Component
{
    use WithFileUploads;
    use UsesSpamProtection;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

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
    public function mount(): void
    {
        $this->honeypotData = new HoneypotData();

        $this->authorize('create', Mod::class);
    }

    /**
     * Save the mod.
     */
    public function save(): void
    {
        $this->authorize('create', Mod::class);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Parse the published at date in the user's timezone, falling back to UTC if the user has no timezone, and
        // convert it to UTC for DB storage.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Carbon::parse($this->publishedAt, $userTimezone)
                ->setTimezone('UTC')
                ->toDateTimeString();
        }

        // Create a new mod instance.
        $mod = new Mod([
            'owner_id' => auth()->user()->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'source_code_url' => $this->sourceCodeUrl,
            'contains_ai_content' => $this->containsAiContent,
            'contains_ads' => $this->containsAds,
            'published_at' => $this->publishedAt,
        ]);

        // Set the thumbnail if an avatar was uploaded.
        if ($this->avatar !== null) {
            $mod->thumbnail = $this->avatar->storePublicly(
                path: 'mods',
                options: config('filesystems.asset_upload', 'public'),
            );
        }

        // Save the mod.
        $mod->save();

        flash()->success('Mod has been Successfully Created');

        $this->redirect($mod->detail_url);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.page.mod.create');
    }
}
