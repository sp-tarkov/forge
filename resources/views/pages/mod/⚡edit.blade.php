<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SourceCodeLink;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;
use Stevebauman\Purify\Facades\Purify;

new #[Layout('layouts::base')] class extends Component {
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
     * @var array<int, array{url: string, label: string|null}>
     */
    public array $sourceCodeLinks = [];

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
     * Whether addons are disabled for the mod.
     */
    public bool $addonsDisabled = false;

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
            ->map(
                fn(SourceCodeLink $link): array => [
                    'url' => $link->url,
                    'label' => $link->label,
                ],
            )
            ->all();

        // Ensure at least one empty link input if no links exist
        if (empty($this->sourceCodeLinks)) {
            $this->sourceCodeLinks[] = ['url' => '', 'label' => ''];
        }

        $this->publishedAt = $this->mod->published_at
            ? Date::parse($this->mod->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC')
                ->format('Y-m-d\TH:i')
            : null;
        $this->containsAiContent = (bool) $this->mod->contains_ai_content;
        $this->containsAds = (bool) $this->mod->contains_ads;
        $this->commentsDisabled = (bool) $this->mod->comments_disabled;
        $this->disableProfileBindingNotice = (bool) $this->mod->profile_binding_notice_disabled;
        $this->addonsDisabled = (bool) $this->mod->addons_disabled;

        // Load existing authors
        $this->authorIds = $this->mod->additionalAuthors->pluck('id')->toArray();
    }

    /**
     * Preview markdown content.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (empty(mb_trim($content))) {
            return '<p class="text-slate-400 dark:text-slate-500 italic">' . __('Nothing to preview.') . '</p>';
        }

        $converter = new GithubFlavoredMarkdownConverter();
        $html = $converter->convert($content)->getContent();

        return Purify::config($purifyConfig)->clean($html);
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
        if (!$validated) {
            return;
        }

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        // Zero out seconds for consistency with datetime-local input format.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Date::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        // Update mod fields
        $this->mod->name = $this->name;
        $this->mod->slug = Str::slug($this->name);
        $this->mod->guid = $this->guid ?: '';
        $this->mod->teaser = $this->teaser;
        $this->mod->description = $this->description;
        $this->mod->license_id = (int) $this->license;
        $this->mod->category_id = (int) $this->category;
        $this->mod->contains_ai_content = $this->containsAiContent;
        $this->mod->contains_ads = $this->containsAds;
        $this->mod->comments_disabled = $this->commentsDisabled;
        $this->mod->profile_binding_notice_disabled = $this->disableProfileBindingNotice;
        $this->mod->addons_disabled = $this->addonsDisabled;
        $this->mod->published_at = $publishedAtCarbon;

        // Set the thumbnail if a file was uploaded.
        if ($this->thumbnail !== null) {
            // Delete the old thumbnail file from storage
            if ($this->mod->thumbnail) {
                Storage::disk(config('filesystems.asset_upload', 'public'))->delete($this->mod->thumbnail);
            }

            // Store the new thumbnail.
            $this->mod->thumbnail = $this->thumbnail->storePublicly(path: 'mods', options: config('filesystems.asset_upload', 'public'));

            // Calculate and store the hash of the uploaded thumbnail
            $this->mod->thumbnail_hash = md5($this->thumbnail->get());
        }

        $this->mod->save();

        // Update source code links
        $this->mod->sourceCodeLinks()->delete();
        foreach ($this->sourceCodeLinks as $link) {
            if (!empty($link['url'])) {
                $this->mod->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        // Update authors (sync will add/remove as needed)
        $this->mod->additionalAuthors()->sync($this->authorIds);

        Track::event(TrackingEventType::MOD_EDIT, $this->mod);

        Session::flash('success', 'Mod has been Successfully Updated');

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // GUID is required if any existing mod version targets SPT >= 4.0.0
        $guidRules = $this->isGuidRequired ? 'required|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,' . $this->mod->id : 'nullable|string|max:255|regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/|unique:mods,guid,' . $this->mod->id;

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
            'sourceCodeLinks.*.label' => 'string|max:50',
            'publishedAt' => 'nullable|date',
            'containsAiContent' => 'boolean',
            'containsAds' => 'boolean',
            'commentsDisabled' => 'boolean',
            'authorIds' => 'array|max:10',
            'authorIds.*' => 'exists:users,id|distinct',
            'disableProfileBindingNotice' => 'boolean',
            'addonsDisabled' => 'boolean',
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
            $matchingVersions = Semver::satisfiedBy($allSptVersions, $constraint);

            // Check if any matching version is >= 4.0.0
            foreach ($matchingVersions as $version) {
                if (Semver::satisfies($version, '>=4.0.0')) {
                    return true;
                }
            }
        } catch (\Exception) {
            // If there's an error parsing the constraint, assume it doesn't require GUID
            return false;
        }

        return false;
    }
};
?>

<x-slot:title>
    {!! __('Edit :mod - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Edit your :mod details below.', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.cube-transparent class="w-5 h-5" />
        {{ __('Edit Mod') }}: {{ $mod->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mod Information</h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">You may update your mod details
                        using this form. Changes will be visible immediately after saving.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a
                            href="{{ route('static.community-standards') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Community Standards</a>
                        and the <a
                            href="{{ route('static.content-guidelines') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Content Guidelines</a>.
                    </p>
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-8">
                            @csrf

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Thumbnail') }}</flux:label>
                                <flux:description>
                                    {{ __('Optionally upload an image to use as the mod\'s thumbnail. This will be displayed on the mod page and in search results. The image should be square, JPG or PNG, and no larger than 2MB.') }}
                                </flux:description>
                                <flux:input
                                    type="file"
                                    wire:model.blur="thumbnail"
                                    accept="image/*"
                                />
                                <flux:error name="thumbnail" />
                                <div
                                    wire:loading
                                    wire:target="thumbnail"
                                    class="mt-2"
                                >
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div
                                            class="bg-cyan-500 h-2.5 rounded-full"
                                            style="width: 0%"
                                            wire:loading.class="animate-pulse"
                                        ></div>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-6">
                                    @if ($mod && $mod->thumbnail)
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Current Thumbnail:</p>
                                            <img
                                                src="{{ asset($mod->thumbnail) }}"
                                                class="h-20 w-20 object-cover rounded border border-gray-300 dark:border-gray-700"
                                                alt="Current thumbnail"
                                            >
                                        </div>
                                    @endif
                                    @if ($thumbnail)
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">New Thumbnail:</p>
                                            <img
                                                src="{{ $thumbnail->temporaryUrl() }}"
                                                class="h-20 w-20 object-cover rounded border border-gray-300 dark:border-gray-700"
                                                alt="New thumbnail"
                                            >
                                        </div>
                                        <div>
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                wire:click="removeThumbnail"
                                                type="button"
                                            >
                                                {{ __('Cancel Thumbnail Change') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            </flux:field>

                            <flux:field
                                class="col-span-6"
                                x-data="{ count: $wire.name.length, text: $wire.name }"
                            >
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:description>
                                    {{ __('Make it catchy, short, and sweet. Displayed on the mod page and in search results.') }}
                                </flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="name"
                                    maxlength="75"
                                    x-model="text"
                                    @input="count = text.length"
                                />
                                <div
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    x-text="`Max Length: ${count}/75`"
                                ></div>
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field
                                class="col-span-6"
                                x-data="{ count: $wire.guid.length, text: $wire.guid }"
                            >
                                <flux:label badge="{{ $this->isGuidRequired ? 'Required' : 'Optional' }}">
                                    {{ __('Mod GUID') }}</flux:label>
                                <flux:description>
                                    {{ __('A unique identifier for your mod in reverse domain notation. This GUID should match the one in your mod files and will be used to identify your mod across different systems. Use only lowercase letters, numbers, and dots.') }}
                                    {{ __('Required for mod versions compatible with SPT 4.0.0 and above.') }}
                                </flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="guid"
                                    maxlength="255"
                                    x-model="text"
                                    @input="count = text.length"
                                    placeholder="com.username.modname"
                                />
                                <div
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    x-text="`Max Length: ${count}/255`"
                                ></div>
                                <flux:error name="guid" />
                            </flux:field>

                            <flux:field
                                class="col-span-6"
                                x-data="{ count: $wire.teaser.length, text: $wire.teaser }"
                            >
                                <flux:label>{{ __('Teaser') }}</flux:label>
                                <flux:description>
                                    {{ __('Describe the mod in a few words. This will be displayed on the mod card in search results and the top of the mod page.') }}
                                </flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="teaser"
                                    maxlength="255"
                                    x-model="text"
                                    @input="count = text.length"
                                />
                                <div
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    x-text="`Max Length: ${count}/255`"
                                ></div>
                                <flux:error name="teaser" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="description"
                                    name="description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain the mod in detail. This will be displayed on the mod page. Use markdown for formatting.',
                                    )"
                                    placeholder="My mod is a *great mod* that does something..."
                                    rows="6"
                                    purify-config="description"
                                />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('License') }}</flux:label>
                                <flux:description>
                                    {{ __('Choose which license your mod is released under. This will be displayed on the mod page.') }}
                                </flux:description>
                                <flux:select
                                    wire:model.blur="license"
                                    placeholder="Choose license..."
                                >
                                    @foreach (\App\Models\License::orderBy('name')->get() as $license)
                                        <flux:select.option value="{{ $license->id }}">{{ $license->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="license" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Category') }}</flux:label>
                                <flux:description>
                                    {{ __('Select the category that best describes your mod. This helps users find your mod more easily.') }}
                                </flux:description>
                                <flux:select
                                    wire:model.live="category"
                                    placeholder="Choose category..."
                                >
                                    @foreach (\App\Models\ModCategory::orderBy('title')->get() as $category)
                                        <flux:select.option value="{{ $category->id }}">{{ $category->title }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="category" />
                            </flux:field>

                            {{-- Author selection --}}
                            <div class="col-span-6">
                                <livewire:form.user-select
                                    :selected-users="$authorIds"
                                    :max-users="10"
                                    :exclude-users="[$mod->owner_id]"
                                    label="Additional Authors"
                                    description="Add other users as co-authors of this mod. The owner cannot be removed and doesn't need to be added here."
                                    placeholder="Search for users by name or email..."
                                />
                            </div>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Source Code Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the source code for your mod. The source code for mods is required to be publicly available. You can add up to 4 links (e.g., main repository, mirror, documentation). We recommend using services like <a href="https://github.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitHub</a> or <a href="https://gitlab.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitLab</a>.',
                                ) !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach ($sourceCodeLinks as $index => $link)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="sourceCodeLinks.{{ $index }}.url"
                                                    placeholder="https://github.com/username/mod-name"
                                                />
                                            </div>
                                            <div class="w-40">
                                                <flux:input
                                                    type="text"
                                                    wire:model.blur="sourceCodeLinks.{{ $index }}.label"
                                                    placeholder="Label (optional)"
                                                />
                                            </div>
                                            @if (count($sourceCodeLinks) > 1)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="removeSourceCodeLink({{ $index }})"
                                                    type="button"
                                                    icon="x-mark"
                                                />
                                            @endif
                                        </div>
                                        @error('sourceCodeLinks.' . $index . '.url')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    @endforeach

                                    @if (count($sourceCodeLinks) < 4)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="addSourceCodeLink"
                                            type="button"
                                            icon="plus"
                                        >
                                            {{ __('Add another link') }}
                                        </flux:button>
                                    @endif
                                </div>

                                <flux:error name="sourceCodeLinks" />
                            </flux:field>

                            <flux:field
                                class="col-span-6"
                                x-data="{
                                    now() {
                                        // Format: YYYY-MM-DDTHH:MM
                                        const pad = n => n.toString().padStart(2, '0');
                                        const d = new Date();
                                        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                    }
                                }"
                            >
                                <flux:label badge="Optional">{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    {!! __(
                                        'Select the date and time the mod will be published. If the mod is not published, it will not be discoverable by other users. Leave blank to keep the mod unpublished.',
                                    ) !!}
                                    @if (auth()->user()->timezone === null)
                                        <flux:callout
                                            icon="exclamation-triangle"
                                            color="orange"
                                            inline="inline"
                                            class="my-2"
                                        >
                                            <flux:callout.text>
                                                You have not selected a timezone for your account. You may continue, but
                                                the published date will be interpreted as a UTC date. Alternatively, you
                                                can <a
                                                    href="/user/profile"
                                                    class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                                                >edit your profile</a> to set a specific timezone.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @else
                                        {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                    @endif
                                </flux:description>
                                <div class="flex gap-2 items-center">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.defer="publishedAt"
                                    />
                                    @if (auth()->user()->timezone !== null)
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            @click="$wire.set('publishedAt', now())"
                                        >Now</flux:button>
                                    @endif
                                </div>
                                <flux:error name="publishedAt" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Disclosure">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAiContent"
                                        label="Contains AI Content"
                                        description="This mod contains content that was generated by AI."
                                    />
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAds"
                                        label="Contains Ads"
                                        description="This mod contains advertisements for products, services, or other content."
                                    />
                                    @if ($this->shouldShowProfileBindingField())
                                        <flux:checkbox
                                            value="true"
                                            wire:model.blur="disableProfileBindingNotice"
                                            label="Disable Profile Binding Notice"
                                            description="Check this option if you can confirm that your mod does not make permanent changes to user profiles. Mods in the category you've selected typically do. Leave this unchecked if you are not sure."
                                        />
                                    @endif
                                </flux:checkbox.group>
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Comments">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="commentsDisabled"
                                        label="Disable Comments"
                                        description="When enabled, normal users will not be able to view or create comments on this mod. Staff and moderators will still have full access."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Add-ons">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="addonsDisabled"
                                        label="Disable Add-ons"
                                        description="When enabled, users will not be able to create or view add-ons for this mod. Use this if your mod does not support or allow add-ons."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                            type="submit"
                        >{{ __('Update Mod') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
