<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use App\Models\ModCategory;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
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
     * The category of the mod.
     */
    public string $category = '';

    /**
     * The source code links of the mod.
     *
     * @var array<int, array{url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [
        ['url' => '', 'label' => ''],
    ];

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
     * Whether to subscribe to comment notifications for the mod.
     */
    public bool $subscribeToComments = true;

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
     * Mount the component.
     */
    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;

        $this->authorize('create', Mod::class);
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
     * Check if the selected category shows profile binding notice by default.
     */
    public function shouldShowProfileBindingField(): bool
    {
        if (empty($this->category)) {
            return false;
        }

        $category = ModCategory::query()->find($this->category);

        return $category && $category->shows_profile_binding_notice;
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
        // convert it to UTC for DB storage. Zero out seconds for consistency with datetime-local input format.
        if ($this->publishedAt !== null) {
            $userTimezone = auth()->user()->timezone ?? 'UTC';
            $this->publishedAt = Carbon::parse($this->publishedAt, $userTimezone)
                ->setTimezone('UTC')
                ->second(0)
                ->toDateTimeString();
        }

        // Create a new mod instance.
        $mod = new Mod([
            'owner_id' => auth()->user()->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'guid' => $this->guid ?: '',
            'teaser' => $this->teaser,
            'description' => $this->description,
            'license_id' => $this->license,
            'category_id' => (int) $this->category,
            'contains_ai_content' => $this->containsAiContent,
            'contains_ads' => $this->containsAds,
            'comments_disabled' => $this->commentsDisabled,
            'profile_binding_notice_disabled' => $this->disableProfileBindingNotice,
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

        // Add authors
        if (! empty($this->authorIds)) {
            $mod->authors()->attach($this->authorIds);
        }

        // Add source code links
        foreach ($this->sourceCodeLinks as $link) {
            if (! empty($link['url'])) {
                $mod->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        // Subscribe the owner to comment notifications if requested.
        if ($this->subscribeToComments) {
            $mod->subscribeUser(auth()->user());
        }

        Track::event(TrackingEventType::MOD_CREATE, $mod);

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
        return view('livewire.page.mod.create');
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
            'guid' => 'nullable|string|max:255|regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/|unique:mods,guid',
            'teaser' => 'required|string|max:255',
            'description' => 'required|string',
            'license' => 'required|exists:licenses,id',
            'category' => 'required|exists:mod_categories,id',
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
            'disableProfileBindingNotice' => 'boolean',
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
