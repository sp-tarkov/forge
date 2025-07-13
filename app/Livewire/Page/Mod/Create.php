<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class Create extends Component
{
    use UsesSpamProtection;
    use WithFileUploads;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The thumbnail of the mod.
     */
    public ?UploadedFile $thumbnail = null;

    /**
     * The name of the mod.
     */
    public string $name = '';

    /**
     * The mod GUID in reverse domain notation.
     */
    public string $guid = '';

    /**
     * The teaser of the mod.
     */
    public string $teaser = '';

    /**
     * The description of the mod.
     */
    public string $description = '';

    /**
     * The license of the mod.
     */
    public string $license = '';

    /**
     * The source code URL of the mod.
     */
    public string $sourceCodeUrl = '';

    /**
     * The published at date of the mod.
     */
    public ?string $publishedAt = null;

    /**
     * Whether the mod contains AI content.
     */
    public bool $containsAiContent = false;

    /**
     * Whether the mod contains ads.
     */
    public bool $containsAds = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;

        $this->authorize('create', Mod::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'name' => 'required|string|max:75',
            'guid' => 'required|string|max:255|regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/|unique:mods,guid',
            'teaser' => 'required|string|max:255',
            'description' => 'required|string',
            'license' => 'required|exists:licenses,id',
            'sourceCodeUrl' => 'required|url|starts_with:https://,http://',
            'publishedAt' => 'nullable|date',
            'containsAiContent' => 'boolean',
            'containsAds' => 'boolean',
        ];
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
            'guid' => $this->guid,
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'source_code_url' => $this->sourceCodeUrl,
            'contains_ai_content' => $this->containsAiContent,
            'contains_ads' => $this->containsAds,
            'published_at' => $this->publishedAt,
        ]);

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail !== null) {
            $mod->thumbnail = $this->thumbnail->storePublicly(
                path: 'mods',
                options: config('filesystems.asset_upload', 'public'),
            );

            // Calculate and store the hash of the uploaded thumbnail
            $mod->thumbnail_hash = md5($this->thumbnail->get());
        }

        // Save the mod.
        $mod->save();

        flash()->success('Mod has been Successfully Created');

        $this->redirect($mod->detail_url);
    }

    /**
     * Remove the uploaded thumbnail.
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod.create');
    }
}
