<?php

declare(strict_types=1);

namespace App\Livewire\Page\Addon;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class Create extends Component
{
    use RendersMarkdownPreview;
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
     * @var array<int, array{url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [
        ['url' => '', 'label' => ''],
    ];

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
        $this->honeypotData = new HoneypotData;

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

        // Parse the published at date in the user's timezone, falling back to UTC if the user has no timezone, and
        // convert it to UTC for DB storage. Zero out seconds for consistency with datetime-local input format.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Date::parse($this->publishedAt, $userTimezone)
                ->setTimezone('UTC')
                ->second(0)
                ->toDateTimeString();
        }

        // Create a new addon instance.
        $addon = new Addon([
            'mod_id' => $this->mod->id,
            'owner_id' => auth()->user()->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'contains_ai_content' => $this->containsAiContent,
            'contains_ads' => $this->containsAds,
            'comments_disabled' => $this->commentsDisabled,
            'published_at' => $this->publishedAt,
        ]);

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail !== null) {
            $addon->thumbnail = $this->thumbnail->storePublicly(
                path: 'addons',
                options: config('filesystems.asset_upload', 'public'),
            );

            // Calculate and store the hash of the uploaded thumbnail
            $addon->thumbnail_hash = md5($this->thumbnail->get());
        }

        // Save the addon.
        $addon->save();

        // Add authors
        if (! empty($this->authorIds)) {
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
        if ($this->subscribeToComments) {
            $addon->subscribeUser(auth()->user());
        }

        Track::event(TrackingEventType::ADDON_CREATE, $addon);

        Session::flash('success', 'Addon has been Successfully Created');

        $this->redirect($addon->detail_url);
    }

    /**
     * Remove the uploaded thumbnail.
     */
    public function removeThumbnail(): void
    {
        $this->thumbnail = null;
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
        return view('livewire.page.addon.create');
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
}
