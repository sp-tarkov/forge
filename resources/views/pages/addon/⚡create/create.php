<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use Flux\Flux;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
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
    public array $sourceCodeLinks = [['key' => 'link-0', 'url' => '', 'label' => '']];

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
     * The custom AI disclosure message.
     */
    public string $customAiDisclosure = '';

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
    public bool $subscribeToComments = true;

    /**
     * The selected author user IDs.
     *
     * @var array<int>
     */
    public array $authorIds = [];

    /**
     * The parent mod for this addon.
     */
    public Mod $mod;

    /**
     * Mount the component.
     */
    public function mount(Mod $mod): void
    {
        $this->honeypotData = new HoneypotData();

        $this->mod = $mod;

        $this->authorize('create', [Addon::class, $this->mod]);
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
        $this->authorize('create', [Addon::class, $this->mod]);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Validate the form.
        $validated = $this->validate();
        if (! $validated) {
            return;
        }

        // Combine date and time into a single published_at value, converting from user timezone to UTC.
        $publishedAt = null;
        if ($this->publishedAtDate !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $dateTimeString = $this->publishedAtDate.' '.($this->publishedAtTime ?? '00:00');
            $publishedAt = Date::parse($dateTimeString, $userTimezone)->setTimezone('UTC')->second(0)->toDateTimeString();
        }

        // Create a new addon instance.
        $addon = new Addon([
            'mod_id' => $this->mod->id,
            'owner_id' => auth()->user()?->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'contains_ai_content' => $this->containsAiContent,
            'custom_ai_disclosure' => $this->containsAiContent && $this->customAiDisclosure !== '' ? $this->customAiDisclosure : null,
            'contains_ads' => $this->containsAds,
            'comments_disabled' => $this->commentsDisabled,
            'published_at' => $publishedAt,
        ]);

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail instanceof UploadedFile) {
            /** @var string $diskName */
            $diskName = config('filesystems.asset_upload', 'public');
            $thumbnailPath = $this->thumbnail->storePublicly(path: 'addons', options: $diskName);
            if ($thumbnailPath !== false) {
                $addon->thumbnail = $thumbnailPath;
            }

            // Calculate and store the hash of the uploaded thumbnail
            $fileContents = $this->thumbnail->get();
            if ($fileContents !== false) {
                $addon->thumbnail_hash = md5($fileContents);
            }
        }

        // Save the addon.
        $addon->save();

        // Add authors
        if ($this->authorIds !== []) {
            $addon->additionalAuthors()->attach($this->authorIds);
        }

        // Add source code links
        foreach ($this->sourceCodeLinks as $link) {
            if (! empty($link['url'])) {
                $addon->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        // Subscribe the owner to comment notifications if requested.
        $currentUser = auth()->user();
        if ($this->subscribeToComments && $currentUser !== null) {
            $addon->subscribeUser($currentUser);
        }

        Track::event(TrackingEventType::ADDON_CREATE, $addon);

        Flux::toast(heading: 'Addon Created', text: 'Your addon has been successfully created.', variant: 'success');

        $this->redirect($addon->detail_url, navigate: true);
    }

    /**
     * Remove the uploaded thumbnail.
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
        $this->resetErrorBag('thumbnail');
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
            'customAiDisclosure' => 'required_if:containsAiContent,true|string|max:1000',
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
            'customAiDisclosure.required_if' => 'Please describe how AI was used when your addon contains AI content.',
        ];
    }
};
