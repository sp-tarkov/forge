<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\License;
use App\Models\SourceCodeLink;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;
use Stevebauman\Purify\Facades\Purify;

new #[Layout('layouts::base')] class extends Component
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
     * @var array<int, array{key: string, url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [];

    /**
     * The published at date of the addon.
     */
    public ?string $publishedAtDate = null;

    /**
     * The published at time of the addon.
     */
    public ?string $publishedAtTime = null;

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
     * Whether to subscribe to comment notifications for the addon.
     */
    public bool $subscribeToComments = false;

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
        $this->honeypotData = new HoneypotData();

        $this->addon = Addon::query()
            ->with(['sourceCodeLinks', 'additionalAuthors', 'mod'])
            ->findOrFail($addonId);

        $this->authorize('update', $this->addon);

        // Prefill fields from the addon
        $this->name = $this->addon->name;
        $this->teaser = $this->addon->teaser ?? '';
        $this->description = $this->addon->description ?? '';
        $this->license = (string) $this->addon->license_id;

        // Load existing source code links
        $this->sourceCodeLinks = $this->addon->sourceCodeLinks
            ->values()
            ->map(
                fn (SourceCodeLink $link, int $index): array => [
                    'key' => 'link-'.$index,
                    'url' => $link->url,
                    'label' => $link->label,
                ],
            )
            ->all();

        // Ensure at least one empty link input if no links exist
        if ($this->sourceCodeLinks === []) {
            $this->sourceCodeLinks[] = ['key' => 'link-0', 'url' => '', 'label' => ''];
        }

        if ($this->addon->published_at) {
            $publishedAtLocal = Date::parse($this->addon->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC');
            $this->publishedAtDate = $publishedAtLocal->format('Y-m-d');
            $this->publishedAtTime = $publishedAtLocal->format('H:i');
        }
        $this->containsAiContent = (bool) $this->addon->contains_ai_content;
        $this->containsAds = (bool) $this->addon->contains_ads;
        $this->commentsDisabled = (bool) $this->addon->comments_disabled;

        // Check if the user is subscribed to comment notifications
        $currentUser = auth()->user();
        $this->subscribeToComments = $currentUser !== null && $this->addon->isUserSubscribed($currentUser);

        // Load existing authors
        /** @var array<int> $authorIds */
        $authorIds = $this->addon->additionalAuthors->pluck('id')->toArray();
        $this->authorIds = $authorIds;
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
     * Get all licenses ordered by name.
     *
     * @return Collection<int, License>
     */
    #[Computed]
    public function licenses(): Collection
    {
        return License::cachedOrdered();
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

        // Combine date and time into a single published_at value, converting from user timezone to UTC.
        $publishedAtCarbon = null;
        if ($this->publishedAtDate !== null && $this->publishedAtDate !== '') {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $dateTimeString = $this->publishedAtDate.' '.($this->publishedAtTime ?? '00:00');
            $publishedAtCarbon = Date::parse($dateTimeString, $userTimezone)->setTimezone('UTC')->second(0);
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
        $this->addon->published_at = $publishedAtCarbon; // @phpstan-ignore assign.propertyType

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail instanceof UploadedFile) {
            // Delete the old thumbnail file from storage
            if ($this->addon->thumbnail) {
                /** @var string $diskName */
                $diskName = config('filesystems.asset_upload', 'public');
                Storage::disk($diskName)->delete($this->addon->thumbnail);
            }

            // Store the new thumbnail.
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            $thumbnailPath = $this->thumbnail->storePublicly(path: 'addons', options: $diskName);
            if ($thumbnailPath !== false) {
                $this->addon->thumbnail = $thumbnailPath;
            }

            // Calculate and store the hash of the uploaded thumbnail
            $fileContents = $this->thumbnail->get();
            if ($fileContents !== false) {
                $this->addon->thumbnail_hash = md5($fileContents);
            }
        }

        // Save the addon.
        $this->addon->save();

        // Sync authors (this will remove old ones and add new ones)
        $this->addon->additionalAuthors()->sync($this->authorIds);

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

        // Handle comment subscription
        $currentUser = auth()->user();
        if ($currentUser !== null) {
            if ($this->subscribeToComments && ! $this->addon->isUserSubscribed($currentUser)) {
                $this->addon->subscribeUser($currentUser);
            } elseif (! $this->subscribeToComments && $this->addon->isUserSubscribed($currentUser)) {
                $this->addon->unsubscribeUser($currentUser);
            }
        }

        Track::event(TrackingEventType::ADDON_EDIT, $this->addon);

        Flux::toast(text: 'Addon has been Successfully Updated');

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
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            Storage::disk($diskName)->delete($this->addon->thumbnail);
            $this->addon->thumbnail = null;
            $this->addon->thumbnail_hash = null;
            $this->addon->save();

            Flux::toast(text: 'Thumbnail has been deleted');
        }
    }

    /**
     * Add a new source code link input.
     */
    public function addSourceCodeLink(): void
    {
        if (count($this->sourceCodeLinks) < 4) {
            $this->sourceCodeLinks[] = ['key' => uniqid('link-'), 'url' => '', 'label' => ''];
        }
    }

    /**
     * Remove a source code link input.
     */
    public function removeSourceCodeLink(int $index): void
    {
        if (count($this->sourceCodeLinks) > 1) {
            array_splice($this->sourceCodeLinks, $index, 1);
        }
    }

    /**
     * Render markdown content to HTML for preview.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (in_array(mb_trim($content), ['', '0'], true)) {
            return '<p class="text-slate-400 dark:text-slate-500 italic">'.__('Nothing to preview.').'</p>';
        }

        $html = Markdown::convert($content)->getContent();

        /** @var string */
        return Purify::config($purifyConfig)->clean($html);
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
            'sourceCodeLinks.*.label' => 'nullable|string|max:50',
            'publishedAtDate' => 'nullable|date',
            'publishedAtTime' => 'nullable|date_format:H:i',
            'containsAiContent' => 'boolean',
            'containsAds' => 'boolean',
            'commentsDisabled' => 'boolean',
            'subscribeToComments' => 'boolean',
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
};
