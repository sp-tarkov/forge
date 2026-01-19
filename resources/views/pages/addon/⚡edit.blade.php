<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\SourceCodeLink;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->teaser = $this->addon->teaser;
        $this->description = $this->addon->description;
        $this->license = (string) $this->addon->license_id;

        // Load existing source code links
        $this->sourceCodeLinks = $this->addon->sourceCodeLinks
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

        $this->publishedAt = $this->addon->published_at
            ? Date::parse($this->addon->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC')
                ->format('Y-m-d\TH:i')
            : null;
        $this->containsAiContent = (bool) $this->addon->contains_ai_content;
        $this->containsAds = (bool) $this->addon->contains_ads;
        $this->commentsDisabled = (bool) $this->addon->comments_disabled;

        // Check if the user is subscribed to comment notifications
        $this->subscribeToComments = $this->addon->isUserSubscribed(auth()->user());

        // Load existing authors
        $this->authorIds = $this->addon->additionalAuthors->pluck('id')->toArray();
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
            $this->addon->thumbnail = $this->thumbnail->storePublicly(path: 'addons', options: config('filesystems.asset_upload', 'public'));

            // Calculate and store the hash of the uploaded thumbnail
            $this->addon->thumbnail_hash = md5($this->thumbnail->get());
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
            if (!empty($link['url'])) {
                $this->addon->sourceCodeLinks()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? '',
                ]);
            }
        }

        // Handle comment subscription
        if ($this->subscribeToComments && !$this->addon->isUserSubscribed(auth()->user())) {
            $this->addon->subscribeUser(auth()->user());
        } elseif (!$this->subscribeToComments && $this->addon->isUserSubscribed(auth()->user())) {
            $this->addon->unsubscribeUser(auth()->user());
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
     * Render markdown content to HTML for preview.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (empty(mb_trim($content))) {
            return '<p class="text-slate-400 dark:text-slate-500 italic">' . __('Nothing to preview.') . '</p>';
        }

        $html = Markdown::convert($content)->getContent();

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
};
?>

<x-slot:title>
    {!! __('Edit :addon Addon - The Forge', ['addon' => $addon->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Edit the :addon addon.', ['addon' => $addon->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.puzzle-piece class="w-5 h-5" />
        {{ __('Edit Addon') }}: {{ $addon->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Addon Information</h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Update the information for <strong>{{ $addon->name }}</strong>, an addon for
                        <strong>{{ $addon->mod?->name ?? 'Unknown Mod' }}</strong>.
                    </p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Changes will be visible immediately after saving. Please ensure your content follows the <a
                            href="{{ route('static.community-standards') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Community Standards</a>.
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-8">
                            @csrf

                            {{-- Existing Thumbnail --}}
                            @if ($addon->thumbnail && !$thumbnail)
                                <div class="col-span-6">
                                    <flux:label>{{ __('Current Thumbnail') }}</flux:label>
                                    <div class="mt-2 flex items-center gap-4">
                                        <img
                                            src="{{ $addon->thumbnailUrl }}"
                                            class="h-32 w-32 object-cover rounded"
                                            alt="Current thumbnail"
                                        >
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            wire:click="deleteExistingThumbnail"
                                            wire:confirm="Are you sure you want to delete the current thumbnail?"
                                            type="button"
                                        >
                                            {{ __('Delete Thumbnail') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif

                            {{-- Thumbnail Upload --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Thumbnail') }}</flux:label>
                                <flux:description>
                                    {{ __('Upload a new thumbnail image. The image should be square, JPG or PNG, and no larger than 2MB.') }}
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
                                        <div class="bg-cyan-500 h-2.5 rounded-full animate-pulse"></div>
                                    </div>
                                </div>
                                @if ($thumbnail)
                                    <div class="mt-2 flex items-center gap-2">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">New thumbnail preview:
                                            </p>
                                            <img
                                                src="{{ $thumbnail->temporaryUrl() }}"
                                                class="h-20 w-20 object-cover rounded"
                                                alt="New thumbnail preview"
                                            >
                                        </div>
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            wire:click="removeThumbnail"
                                            type="button"
                                        >
                                            {{ __('Remove New Thumbnail') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </flux:field>

                            {{-- Name --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{ text: $wire.entangle('name') }"
                                x-init="$watch('text', value => {})"
                            >
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:description>
                                    {{ __('Make it catchy, short, and sweet. Displayed on the addon page and in search results.') }}
                                </flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="name"
                                    maxlength="75"
                                />
                                <div
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    x-text="`Max Length: ${(text || '').length}/75`"
                                ></div>
                                <flux:error name="name" />
                            </flux:field>

                            {{-- Teaser --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{ text: $wire.entangle('teaser') }"
                                x-init="$watch('text', value => {})"
                            >
                                <flux:label>{{ __('Teaser') }}</flux:label>
                                <flux:description>
                                    {{ __('Describe the addon in a few words. This will be displayed on the addon card in search results and the top of the addon page.') }}
                                </flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="teaser"
                                    maxlength="255"
                                />
                                <div
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    x-text="`Max Length: ${(text || '').length}/255`"
                                ></div>
                                <flux:error name="teaser" />
                            </flux:field>

                            {{-- Description --}}
                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="description"
                                    name="description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain the addon in detail. This will be displayed on the addon page. Use markdown for formatting.',
                                    )"
                                    placeholder="My addon is a *great addon* that does something..."
                                    rows="6"
                                    purify-config="description"
                                />
                            </flux:field>

                            {{-- License --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('License') }}</flux:label>
                                <flux:description>
                                    {{ __('Choose which license your addon is released under. This will be displayed on the addon page.') }}
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

                            {{-- Source Code Links --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Source Code Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the source code for your addon. The source code for addons is required to be publicly available. You can add up to 4 links (e.g., main repository, mirror, documentation). We recommend using services like <a href="https://github.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitHub</a> or <a href="https://gitlab.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitLab</a>.',
                                ) !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach ($sourceCodeLinks as $index => $link)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="sourceCodeLinks.{{ $index }}.url"
                                                    placeholder="https://github.com/username/addon-name"
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

                            {{-- Additional Authors --}}
                            <div class="col-span-6">
                                <livewire:form.user-select
                                    :selected-users="$authorIds"
                                    :max-users="10"
                                    :exclude-users="[auth()->user()->id]"
                                    label="Additional Authors"
                                    description="Add other users as co-authors of this addon. You are automatically listed as the owner and don't need to add yourself here."
                                    placeholder="Search for users by name or email..."
                                />
                            </div>

                            {{-- Published At --}}
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
                                        'Select the date and time the addon will be published. If the addon is not published, it will not be discoverable by other users. Leave blank to keep the addon unpublished.',
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
                                        description="This addon contains content that was generated by AI."
                                    />
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAds"
                                        label="Contains Ads"
                                        description="This addon contains advertisements for products, services, or other content."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Comments">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="commentsDisabled"
                                        label="Disable Comments"
                                        description="When enabled, normal users will not be able to view or create comments on this addon. Staff and moderators will still have full access."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Notifications">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="subscribeToComments"
                                        label="Subscribe to Comment Notifications"
                                        description="When enabled, you will receive notifications when users comment on this addon. You can unsubscribe later from individual notifications."
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
                        >{{ __('Save Changes') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
