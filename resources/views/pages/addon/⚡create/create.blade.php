<x-slot:title>
    {!! __('Create a New Addon for :mod - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Create a new addon for :mod to share with the community.', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.puzzle-piece class="w-5 h-5" />
        {{ __('Create Addon') }}: {{ $mod->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Addon Information</h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Create an addon that extends <strong>{{ $mod->name }}</strong>. Addons are supplemental
                        modifications like music packs, trader avatars, or preset configurations.
                    </p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        After creating the addon, you'll be able to submit addon versions/files with version
                        constraints to specify which mod versions they're compatible with.
                    </p>
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
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-8">
                            @csrf

                            {{-- Thumbnail --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Thumbnail') }}</flux:label>
                                <flux:description>
                                    {{ __('Optionally upload an image to use as the addon\'s thumbnail. This will be displayed on the addon page and in search results. The image should be square, JPG or PNG, and no larger than 2MB.') }}
                                </flux:description>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <flux:file-upload wire:model="thumbnail" @class(['h-full', 'lg:col-span-2' => !$thumbnail])>
                                        <flux:file-upload.dropzone
                                            heading="{{ __('Drop image here or click to browse') }}"
                                            text="{{ __('JPG or PNG, square, up to 2MB') }}"
                                            with-progress
                                            inline
                                            class="h-full"
                                        />
                                    </flux:file-upload>
                                    @if ($thumbnail)
                                        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-3">
                                            <img
                                                src="{{ $thumbnail->temporaryUrl() }}"
                                                class="size-20 shrink-0 rounded-lg object-cover"
                                                alt="{{ __('Thumbnail preview') }}"
                                            >
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $thumbnail->getClientOriginalName() }}</p>
                                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ Number::fileSize($thumbnail->getSize(), 1) }}</p>
                                            </div>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="x-mark"
                                                wire:click="removeThumbnail"
                                                type="button"
                                            />
                                        </div>
                                    @endif
                                </div>
                                <flux:error name="thumbnail" />
                            </flux:field>

                            {{-- Name --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{ count: 0, text: '' }"
                            >
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:description>
                                    {{ __('Make it catchy, short, and sweet. Displayed on the addon page and in search results.') }}
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

                            {{-- Teaser --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{ count: 0, text: '' }"
                            >
                                <flux:label>{{ __('Teaser') }}</flux:label>
                                <flux:description>
                                    {{ __('Describe the addon in a few words. This will be displayed on the addon card in search results and the top of the addon page.') }}
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
                                    variant="listbox"
                                    searchable
                                    wire:model="license"
                                    placeholder="Choose license..."
                                >
                                    @foreach ($this->licenses as $license)
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

                                <div
                                    x-data="{
                                        links: $wire.sourceCodeLinks,
                                        counter: $wire.sourceCodeLinks.length,
                                        addLink() {
                                            if (this.links.length < 4) {
                                                this.links.push({ key: 'link-' + this.counter++, url: '', label: '' });
                                            }
                                        },
                                        removeLink(index) {
                                            if (this.links.length > 1) {
                                                this.links.splice(index, 1);
                                            }
                                        },
                                    }"
                                    x-effect="$wire.sourceCodeLinks = links"
                                    class="space-y-3"
                                >
                                    <template
                                        x-for="(link, index) in links"
                                        :key="link.key"
                                    >
                                        <div>
                                            <div class="flex gap-2 items-center">
                                                <div class="flex-1">
                                                    <input
                                                        type="url"
                                                        x-model.lazy="link.url"
                                                        placeholder="https://github.com/username/addon-name"
                                                        class="w-full border rounded-lg appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 text-zinc-700 placeholder-zinc-400 dark:text-zinc-300 dark:placeholder-zinc-400 shadow-xs border-zinc-200 border-b-zinc-300/80 dark:border-white/10"
                                                    />
                                                </div>
                                                <div class="w-40">
                                                    <input
                                                        type="text"
                                                        x-model.lazy="link.label"
                                                        placeholder="Label (optional)"
                                                        class="w-full border rounded-lg appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 text-zinc-700 placeholder-zinc-400 dark:text-zinc-300 dark:placeholder-zinc-400 shadow-xs border-zinc-200 border-b-zinc-300/80 dark:border-white/10"
                                                    />
                                                </div>
                                                <button
                                                    x-show="links.length > 1"
                                                    x-on:click="removeLink(index)"
                                                    type="button"
                                                    class="inline-flex items-center justify-center p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors"
                                                >
                                                    <flux:icon.x-mark class="size-5" />
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <button
                                        x-show="links.length < 4"
                                        x-on:click="addLink()"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors"
                                    >
                                        <flux:icon.plus class="size-4" />
                                        {{ __('Add another link') }}
                                    </button>
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
                                    <flux:date-picker
                                        wire:model="publishedAtDate"
                                        clearable
                                    />
                                    <flux:time-picker
                                        wire:model="publishedAtTime"
                                        clearable
                                    />
                                    @if (auth()->user()->timezone !== null)
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            @click="$wire.set('publishedAtDate', '{{ now()->timezone(auth()->user()->timezone)->format('Y-m-d') }}'); $wire.set('publishedAtTime', '{{ now()->timezone(auth()->user()->timezone)->format('H:i') }}')"
                                        >Now</flux:button>
                                    @endif
                                </div>
                                <flux:error name="publishedAtDate" />
                                <flux:error name="publishedAtTime" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="General Disclosure">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAds"
                                        label="Contains Ads"
                                        description="This addon contains advertisements for products, services, or other content."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <div
                                class="col-span-6"
                                x-data="{ aiContent: @entangle('containsAiContent') }"
                            >
                                <flux:field>
                                    <flux:checkbox.group label="AI Disclosure">
                                        <flux:checkbox
                                            value="true"
                                            x-model="aiContent"
                                            label="Contains AI Content"
                                            description="This addon contains content or code that was partially or wholly generated by AI."
                                        />
                                    </flux:checkbox.group>
                                    <flux:field
                                        class="mt-4"
                                        x-show="aiContent"
                                        x-cloak
                                    >
                                        <flux:label badge="Optional">{{ __('Custom Disclosure Message') }}</flux:label>
                                        <flux:description>
                                            {{ __('Optionally describe how AI was used in this project. This message will appear on the addon page when users expand the AI disclosure. Markdown is supported.') }}
                                        </flux:description>
                                        <x-markdown-editor
                                            wire-model="customAiDisclosure"
                                            name="customAiDisclosure"
                                            placeholder="This project used AI to..."
                                            rows="4"
                                            purify-config="description"
                                            maxlength="1000"
                                        />
                                    </flux:field>
                                </flux:field>
                            </div>

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
                        >{{ __('Create Addon') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>