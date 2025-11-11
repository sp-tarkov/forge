<?php

declare(strict_types=1);

namespace App\Livewire\Page\Addon;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\SourceCodeLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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
     * The addon being edited.
     */
    public Addon $addon;

    /**
     * The thumbnail of the addon.
     */
    public ?UploadedFile $thumbnail = null;

    /**
     * The name of the addon.
     */
    public string $name = '';

    /**
     * The teaser of the addon.
     */
    public string $teaser = '';

    /**
     * The description of the addon.
     */
    public string $description = '';

    /**
     * The license of the addon.
     */
    public string $license = '';

    /**
     * The source code links of the addon.
     *
     * @var array<int, array{url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [];

    /**
     * The published at date of the addon.
     */
    public ?string $publishedAt = null;

    /**
     * Whether the addon contains AI content.
     */
    public bool $containsAiContent = false;

    /**
     * Whether the addon contains ads.
     */
    public bool $containsAds = false;

    /**
     * Whether comments are disabled for the addon.
     */
    public bool $commentsDisabled = false;

    /**
     * The selected author user IDs.
     *
     * @var array<int>
     */
    public array $authorIds = [];

    /**
     * Mount the component.
     */
    public function mount(int $addonId): void
    {
        $this->honeypotData = new HoneypotData;

        $this->addon = Addon::query()->with(['sourceCodeLinks', 'authors', 'mod'])->findOrFail($addonId);

        $this->authorize('update', $this->addon);

        // Prefill fields from the addon
        $this->name = $this->addon->name;
        $this->teaser = $this->addon->teaser;
        $this->description = $this->addon->description;
        $this->license = (string) $this->addon->license_id;

        // Load existing source code links
        $this->sourceCodeLinks = $this->addon->sourceCodeLinks->map(fn (SourceCodeLink $link): array => [
            'url' => $link->url,
            'label' => $link->label,
        ])->all();

        // Ensure at least one empty link input if no links exist
        if (empty($this->sourceCodeLinks)) {
            $this->sourceCodeLinks[] = ['url' => '', 'label' => ''];
        }

        $this->publishedAt = $this->addon->published_at ? Date::parse($this->addon->published_at)->setTimezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d\TH:i') : null;
        $this->containsAiContent = (bool) $this->addon->contains_ai_content;
        $this->containsAds = (bool) $this->addon->contains_ads;
        $this->commentsDisabled = (bool) $this->addon->comments_disabled;

        // Load existing authors
        $this->authorIds = $this->addon->authors->pluck('id')->toArray();
    }

    /**
     * Update the author IDs from the child component.
     *
     * @param  array<int>  $ids
     */
    #[On('updateAuthorIds')]
    public function updateAuthorIds(array $ids): void
    {
        $this->authorIds = $ids;
    }

    /**
     * Save the addon.
     */
    public function save(): void
    {
        $this->authorize('update', $this->addon);

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
            $publishedAtCarbon = Date::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        // Update addon fields
        $this->addon->name = $this->name;
        $this->addon->slug = Str::slug($this->name);
        $this->addon->teaser = $this->teaser;
        $this->addon->description = $this->description;
        $this->addon->license_id = (int) $this->license;
        $this->addon->contains_ai_content = $this->containsAiContent;
        $this->addon->contains_ads = $this->containsAds;
        $this->addon->comments_disabled = $this->commentsDisabled;
        $this->addon->published_at = $publishedAtCarbon;

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail !== null) {

            // Delete the old thumbnail file from storage
            if ($this->addon->thumbnail) {
                Storage::disk(config('filesystems.asset_upload', 'public'))->delete($this->addon->thumbnail);
            }

            // Store the new thumbnail.
            $this->addon->thumbnail = $this->thumbnail->storePublicly(
                path: 'addons',
                options: config('filesystems.asset_upload', 'public'),
            );

            // Calculate and store the hash of the uploaded thumbnail
            $this->addon->thumbnail_hash = md5($this->thumbnail->get());
        }

        // Save the addon.
        $this->addon->save();

        // Sync authors (this will remove old ones and add new ones)
        $this->addon->authors()->sync($this->authorIds);

        // Sync source code links
        // Delete existing links
        $this->addon->sourceCodeLinks()->delete();

        // Create new links
        foreach ($this->sourceCodeLinks as $link) {
            if (! empty($link['url'])) {
                $this->addon->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        Track::event(TrackingEventType::ADDON_EDIT, $this->addon);

        Session::flash('success', 'Addon has been Successfully Updated');

        $this->redirect($this->addon->detail_url);
    }

    /**
     * Remove the uploaded thumbnail.
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
    }

    /**
     * Delete the existing thumbnail from the addon.
     */
    public function deleteExistingThumbnail(): void
    {
        $this->authorize('update', $this->addon);

        if ($this->addon->thumbnail) {
            Storage::disk(config('filesystems.asset_upload', 'public'))->delete($this->addon->thumbnail);
            $this->addon->thumbnail = null;
            $this->addon->thumbnail_hash = null;
            $this->addon->save();

            flash()->success('Thumbnail has been deleted');
        }
    }

    /**
     * Add a new source code link input.
     */
    public function addSourceCodeLink(): void
    {
        if (count($this->sourceCodeLinks) < 4) {
            $this->sourceCodeLinks[] = ['url' => '', 'label' => ''];
        }
    }

    /**
     * Remove a source code link input.
     */
    public function removeSourceCodeLink(int $index): void
    {
        if (count($this->sourceCodeLinks) > 1) {
            array_splice($this->sourceCodeLinks, $index, 1);
            $this->sourceCodeLinks = array_values($this->sourceCodeLinks);
        }
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.addon.edit');
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
            'teaser' => 'required|string|max:255',
            'description' => 'required|string',
            'license' => 'required|exists:licenses,id',
            'sourceCodeLinks' => 'required|array|min:1|max:4',
            'sourceCodeLinks.*.url' => 'required|url|starts_with:https://,http://',
            'sourceCodeLinks.*.label' => 'string|max:50',
            'publishedAt' => 'nullable|date',
            'containsAiContent' => 'boolean',
            'containsAds' => 'boolean',
            'commentsDisabled' => 'boolean',
            'authorIds' => 'array|max:10',
            'authorIds.*' => 'exists:users,id|distinct',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'sourceCodeLinks.required' => 'At least one source code link is required.',
            'sourceCodeLinks.min' => 'At least one source code link is required.',
            'sourceCodeLinks.max' => 'You can add a maximum of 4 source code links.',
            'sourceCodeLinks.*.url.required' => 'Please enter a valid URL for the source code.',
            'sourceCodeLinks.*.url.url' => 'Please enter a valid URL (e.g., https://github.com/username/repo).',
            'sourceCodeLinks.*.url.starts_with' => 'The URL must start with https:// or http://',
            'sourceCodeLinks.*.label.max' => 'The label must not exceed 50 characters.',
        ];
    }
}
