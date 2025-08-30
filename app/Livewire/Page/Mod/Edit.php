<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
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
     * Whether comments are disabled for the mod.
     */
    public bool $commentsDisabled = false;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->honeypotData = new HoneypotData;

        $this->mod = Mod::query()->findOrFail($modId);

        $this->authorize('update', $this->mod);

        // Prefill fields from the mod
        $this->name = $this->mod->name;
        $this->guid = $this->mod->guid ?? '';
        $this->teaser = $this->mod->teaser;
        $this->description = $this->mod->description;
        $this->license = (string) $this->mod->license_id;
        $this->sourceCodeUrl = $this->mod->source_code_url;
        $this->publishedAt = $this->mod->published_at ? Carbon::parse($this->mod->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d\TH:i') : null;
        $this->containsAiContent = (bool) $this->mod->contains_ai_content;
        $this->containsAds = (bool) $this->mod->contains_ads;
        $this->commentsDisabled = (bool) $this->mod->comments_disabled;
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
            'guid' => 'required|string|max:255|regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/|unique:mods,guid,'.$this->mod->id,
            'teaser' => 'required|string|max:255',
            'description' => 'required|string',
            'license' => 'required|exists:licenses,id',
            'sourceCodeUrl' => 'required|url|starts_with:https://,http://',
            'publishedAt' => 'nullable|date',
            'containsAiContent' => 'boolean',
            'containsAds' => 'boolean',
            'commentsDisabled' => 'boolean',
        ];
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
        // Zero out seconds for consistency with datetime-local input format.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Carbon::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        // Update mod fields
        $this->mod->name = $this->name;
        $this->mod->slug = Str::slug($this->name);
        $this->mod->guid = $this->guid;
        $this->mod->teaser = $this->teaser;
        $this->mod->description = $this->description;
        $this->mod->license_id = (int) $this->license;
        $this->mod->source_code_url = $this->sourceCodeUrl;
        $this->mod->contains_ai_content = $this->containsAiContent;
        $this->mod->contains_ads = $this->containsAds;
        $this->mod->comments_disabled = $this->commentsDisabled;
        $this->mod->published_at = $publishedAtCarbon;

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail !== null) {

            // Delete the old thumbnail file from storage
            if ($this->mod->thumbnail) {
                Storage::disk(config('filesystems.asset_upload', 'public'))->delete($this->mod->thumbnail);
            }

            // Store the new thumbnail.
            $this->mod->thumbnail = $this->thumbnail->storePublicly(
                path: 'mods',
                options: config('filesystems.asset_upload', 'public'),
            );

            // Calculate and store the hash of the uploaded thumbnail
            $this->mod->thumbnail_hash = md5($this->thumbnail->get());
        }

        $this->mod->save();

        Track::event(TrackingEventType::MOD_EDIT, $this->mod);

        flash()->success('Mod has been Successfully Updated');

        $this->redirect($this->mod->detail_url);
    }

    /**
     * Remove the uploaded thumbnail from the form (does not affect the mod's thumbnail until saved).
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
        return view('livewire.page.mod.edit', [
            'mod' => $this->mod,
        ]);
    }
}
