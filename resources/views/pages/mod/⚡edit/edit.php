<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SourceCodeLink;
use App\Models\SptVersion;
use App\Support\VersionMatcher;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

new #[Layout('layouts::base')] class extends Component
{
    use RendersMarkdownPreview;
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
     * The category of the mod.
     */
    public string $category = '';

    /**
     * The source code links of the mod.
     *
     * @var array<int, array{key: string, url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [];

    /**
     * The published at date of the mod.
     */
    public ?string $publishedAtDate = null;

    /**
     * The published at time of the mod.
     */
    public ?string $publishedAtTime = null;

    /**
     * Whether the mod contains AI content.
     */
    public bool $containsAiContent = false;

    /**
     * Whether the contains AI content flag is locked by staff.
     */
    public bool $containsAiContentLocked = false;

    /**
     * The custom AI disclosure message.
     */
    public string $customAiDisclosure = '';

    /**
     * Whether the mod contains ads.
     */
    public bool $containsAds = false;

    /**
     * Whether comments are disabled for the mod.
     */
    public bool $commentsDisabled = false;

    /**
     * The selected author user IDs.
     *
     * @var array<int>
     */
    public array $authorIds = [];

    /**
     * Whether to disable the profile binding notice.
     */
    public bool $disableProfileBindingNotice = false;

    /**
     * Whether to show the cheat notice.
     */
    public bool $cheatNotice = false;

    /**
     * Whether addons are disabled for the mod.
     */
    public bool $addonsDisabled = false;

    /**
     * Whether this mod may be added to user-created mod lists.
     */
    public bool $listsDisabled = false;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->honeypotData = new HoneypotData();

        $this->mod = Mod::query()
            ->with(['sourceCodeLinks', 'additionalAuthors'])
            ->findOrFail($modId);

        $this->authorize('update', $this->mod);

        // Prefill fields from the mod
        $this->name = $this->mod->name;
        $this->guid = $this->mod->guid ?? '';
        $this->teaser = $this->mod->teaser;
        $this->description = $this->mod->description;
        $this->license = (string) $this->mod->license_id;
        $this->category = (string) ($this->mod->category_id ?? '');

        // Load existing source code links
        $this->sourceCodeLinks = $this->mod->sourceCodeLinks
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

        if ($this->mod->published_at) {
            $publishedAtLocal = Date::parse($this->mod->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC');
            $this->publishedAtDate = $publishedAtLocal->format('Y-m-d');
            $this->publishedAtTime = $publishedAtLocal->format('H:i');
        }

        $this->containsAiContent = (bool) $this->mod->contains_ai_content;
        $this->containsAiContentLocked = (bool) $this->mod->contains_ai_content_locked;
        $this->customAiDisclosure = $this->mod->custom_ai_disclosure ?? '';
        $this->containsAds = (bool) $this->mod->contains_ads;
        $this->commentsDisabled = (bool) $this->mod->comments_disabled;
        $this->disableProfileBindingNotice = (bool) $this->mod->profile_binding_notice_disabled;
        $this->cheatNotice = (bool) $this->mod->cheat_notice;
        $this->addonsDisabled = (bool) $this->mod->addons_disabled;
        $this->listsDisabled = (bool) $this->mod->lists_disabled;

        // Load existing authors
        /** @var array<int> $authorIds */
        $authorIds = $this->mod->additionalAuthors->pluck('id')->toArray();
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
     * Whether the current user can lock or unlock the AI content flag.
     */
    #[Computed]
    public function canLockAiContent(): bool
    {
        return auth()->user()?->can('lockAiContent', $this->mod) ?? false;
    }

    /**
     * Whether the AI content flag is currently locked and the user cannot change it.
     */
    #[Computed]
    public function aiContentLockedForUser(): bool
    {
        return $this->mod->contains_ai_content_locked && ! $this->canLockAiContent;
    }

    /**
     * Check if the selected category shows profile binding notice by default.
     */
    public function shouldShowProfileBindingField(): bool
    {
        if ($this->category === '' || $this->category === '0') {
            return false;
        }

        $category = ModCategory::query()->find($this->category);

        return $category && $category->shows_profile_binding_notice;
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
     * Get all mod categories ordered by title.
     *
     * @return Collection<int, ModCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ModCategory::cachedOrdered();
    }

    /**
     * Check if GUID is required based on existing mod versions with SPT >= 4.0.0.
     */
    #[Computed]
    public function isGuidRequired(): bool
    {
        // Check if any mod version has SPT version constraint that includes versions >= 4.0.0
        foreach ($this->mod->versions as $version) {
            if ($this->constraintSatisfiesSpt4OrAbove($version->spt_version_constraint)) {
                return true;
            }
        }

        return false;
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

        // Combine date and time into a single published_at value, converting from user timezone to UTC.
        $publishedAtCarbon = null;
        if ($this->publishedAtDate !== null && $this->publishedAtDate !== '') {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $dateTimeString = $this->publishedAtDate.' '.($this->publishedAtTime ?? '00:00');
            $publishedAtCarbon = Date::parse($dateTimeString, $userTimezone)->setTimezone('UTC')->second(0);
        }

        // Update mod fields
        $this->mod->name = $this->name;
        $this->mod->slug = Str::slug($this->name);
        $this->mod->guid = $this->guid ?: '';
        $this->mod->teaser = $this->teaser;
        $this->mod->description = $this->description;
        $this->mod->license_id = (int) $this->license;
        $this->mod->category_id = (int) $this->category;

        if ($this->canLockAiContent) {
            $this->mod->contains_ai_content_locked = $this->containsAiContentLocked;
            $this->mod->contains_ai_content = $this->containsAiContentLocked ? true : $this->containsAiContent;
        } elseif (! $this->mod->contains_ai_content_locked) {
            $this->mod->contains_ai_content = $this->containsAiContent;
        }

        $this->mod->custom_ai_disclosure = $this->mod->contains_ai_content && $this->customAiDisclosure !== ''
            ? $this->customAiDisclosure
            : null;

        $this->mod->contains_ads = $this->containsAds;
        $this->mod->comments_disabled = $this->commentsDisabled;
        $this->mod->profile_binding_notice_disabled = $this->disableProfileBindingNotice;
        $this->mod->cheat_notice = $this->cheatNotice;
        $this->mod->addons_disabled = $this->addonsDisabled;
        $this->mod->lists_disabled = $this->listsDisabled;
        $this->mod->published_at = $publishedAtCarbon;

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail instanceof UploadedFile) {
            // Delete the old thumbnail file from storage
            if ($this->mod->thumbnail) {
                /** @var string $diskName */
                $diskName = config('filesystems.asset_upload', 'public');
                Storage::disk($diskName)->delete($this->mod->thumbnail);
            }

            // Store the new thumbnail.
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            $thumbnailPath = $this->thumbnail->storePublicly(path: 'mods', options: $diskName);
            if ($thumbnailPath !== false) {
                $this->mod->thumbnail = $thumbnailPath;
            }

            // Calculate and store the hash of the uploaded thumbnail
            $fileContents = $this->thumbnail->get();
            if ($fileContents !== false) {
                $this->mod->thumbnail_hash = md5($fileContents);
            }
        }

        $this->mod->save();

        // Update source code links
        $this->mod->sourceCodeLinks()->delete();
        foreach ($this->sourceCodeLinks as $link) {
            if (! empty($link['url'])) {
                $this->mod->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        // Update authors (sync will add/remove as needed)
        $this->mod->additionalAuthors()->sync($this->authorIds);

        Track::event(TrackingEventType::MOD_EDIT, $this->mod);

        Flux::toast(heading: 'Mod Updated', text: 'Your mod has been successfully updated.', variant: 'success');

        $this->redirect($this->mod->detail_url, navigate: true);
    }

    /**
     * Remove the uploaded thumbnail from the form (does not affect the mod's thumbnail until saved).
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
        $this->resetErrorBag('thumbnail');
    }

    /**
     * Delete the existing thumbnail from the mod.
     */
    public function deleteExistingThumbnail(): void
    {
        $this->authorize('update', $this->mod);

        if ($this->mod->thumbnail) {
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            Storage::disk($diskName)->delete($this->mod->thumbnail);
            $this->mod->thumbnail = '';
            $this->mod->thumbnail_hash = '';
            $this->mod->save();

            Flux::toast(heading: 'Thumbnail Deleted', text: 'The mod thumbnail has been deleted.', variant: 'success');
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // GUID is required if any existing mod version targets SPT >= 4.0.0
        $guidRules = $this->isGuidRequired ? 'required|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,'.$this->mod->id : 'nullable|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,'.$this->mod->id;

        return [
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'name' => 'required|string|max:75',
            'guid' => $guidRules,
            'teaser' => 'required|string|max:255',
            'description' => 'required|string',
            'license' => 'required|exists:licenses,id',
            'category' => 'required|exists:mod_categories,id',
            'sourceCodeLinks' => 'required|array|min:1|max:4',
            'sourceCodeLinks.*.url' => 'required|url|starts_with:https://,http://',
            'sourceCodeLinks.*.label' => 'nullable|string|max:50',
            'publishedAtDate' => 'nullable|date',
            'publishedAtTime' => 'nullable|date_format:H:i',
            'containsAiContent' => 'boolean',
            'containsAiContentLocked' => 'boolean',
            'customAiDisclosure' => 'nullable|string|max:1000',
            'containsAds' => 'boolean',
            'commentsDisabled' => 'boolean',
            'authorIds' => 'array|max:10',
            'authorIds.*' => 'exists:users,id|distinct',
            'disableProfileBindingNotice' => 'boolean',
            'cheatNotice' => 'boolean',
            'addonsDisabled' => 'boolean',
            'listsDisabled' => 'boolean',
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

    /**
     * Check if a version constraint includes SPT 4.0.0 or above.
     */
    private function constraintSatisfiesSpt4OrAbove(string $constraint): bool
    {
        try {
            // Get all valid SPT versions
            $allSptVersions = SptVersion::allValidVersions();

            // Get versions that match the constraint
            $matchingVersions = VersionMatcher::satisfiedBy($allSptVersions, $constraint);

            // Check if any matching version is >= 4.0.0
            foreach ($matchingVersions as $version) {
                if (VersionMatcher::satisfies($version, '>=4.0.0')) {
                    return true;
                }
            }
        } catch (Exception) {
            // If there's an error parsing the constraint, assume it doesn't require GUID
            return false;
        }

        return false;
    }
};
