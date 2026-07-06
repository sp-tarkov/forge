<x-slot:title>{{ __('Create List - The Forge') }}</x-slot>

<x-slot:header>
    <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
        <flux:icon.list-bullet class="h-5 w-5" />
        {{ __('Create a new list') }}
    </h2>
</x-slot>

<div>
    <div class="mx-auto max-w-7xl py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="flex justify-between md:col-span-1">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-100">
                        {{ __('List Details') }}
                    </h3>
                    <p class="my-2 text-sm text-sm/6 text-gray-400">
                        {{ __('Group mods together by theme, compatibility, or personal taste. You can change any of these details later.') }}
                    </p>
                    <x-list.config-guidance class="mt-4" />
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:col-span-2 md:mt-0">
                <form wire:submit="save">
                    <div class="bg-gray-900 px-4 py-5 shadow-sm sm:rounded-tl-md sm:rounded-tr-md sm:p-6">
                        <div class="grid grid-cols-6 gap-8">
                            <flux:field class="col-span-6">
                                <flux:label badge="Optional">{{ __('Thumbnail') }}</flux:label>
                                <flux:description>
                                    {{ __('Upload an image to represent this list. The image should be square, JPG, PNG, or WebP, and no larger than 2 MB.') }}
                                </flux:description>
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    @if ($thumbnail)
                                        <div
                                            class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3">
                                            <img
                                                src="{{ $thumbnail->temporaryUrl() }}"
                                                class="size-20 shrink-0 rounded-lg object-cover"
                                                alt="{{ __('Thumbnail preview') }}"
                                            >
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-zinc-300">
                                                    {{ $thumbnail->getClientOriginalName() }}</p>
                                                <p class="mt-0.5 text-xs text-zinc-400">
                                                    {{ Number::fileSize($thumbnail->getSize(), 1) }}</p>
                                            </div>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="x-mark"
                                                :aria-label="__('Remove selected thumbnail')"
                                                wire:click="removeThumbnail"
                                                type="button"
                                            />
                                        </div>
                                    @endif
                                    <flux:file-upload
                                        wire:model="thumbnail"
                                        @class(['h-full', 'lg:col-span-2' => !$thumbnail])
                                    >
                                        <flux:file-upload.dropzone
                                            heading="{{ __('Drop image here or click to browse') }}"
                                            text="{{ __('JPG, PNG, or WebP, square, up to 2 MB') }}"
                                            with-progress
                                            inline
                                            class="h-full"
                                        />
                                    </flux:file-upload>
                                </div>
                                <flux:error name="thumbnail" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Title') }}</flux:label>
                                <flux:input
                                    wire:model="form.title"
                                    :placeholder="__('My Favourite QoL Mods')"
                                    required
                                    maxlength="{{ config('mod-lists.validation.title_max') }}"
                                />
                                <flux:error name="form.title" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="form.description"
                                    name="form.description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain the list in detail. This will be displayed on the list page. Use markdown for formatting.',
                                    )"
                                    rows="6"
                                    purify-config="description"
                                    maxlength="{{ config('mod-lists.validation.description_max') }}"
                                />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Visibility') }}</flux:label>
                                <flux:select
                                    variant="listbox"
                                    wire:model.live="form.visibility"
                                >
                                    @foreach (\App\Enums\ListVisibility::cases() as $visibility)
                                        <flux:select.option value="{{ $visibility->value }}">
                                            {{ __($visibility->label()) }}: {{ __($visibility->description()) }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="form.visibility" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label badge="Optional">{{ __('Target SPT version') }}</flux:label>
                                <flux:description>
                                    {{ __('If set, list items will show the version compatible with this SPT release.') }}
                                </flux:description>
                                <flux:select
                                    variant="listbox"
                                    searchable
                                    wire:model="form.spt_version_id"
                                >
                                    <flux:select.option value="">{{ __('Latest compatible version') }}
                                    </flux:select.option>
                                    @foreach ($form->availableSptVersions() as $version)
                                        <flux:select.option value="{{ $version->id }}">
                                            {{ $version->version }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="form.spt_version_id" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:switch
                                    wire:model.live="form.comments_disabled"
                                    :label="__('Disable comments')"
                                    :description="$form->visibility === 'private' ? __('Private lists never show a comment thread.') : __('Hide the comment section from everyone, including you.')"
                                    :disabled="$form->visibility === 'private'"
                                />
                            </flux:field>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end gap-3 border-t-2 border-transparent border-t-gray-700 bg-gray-900 px-4 py-3 shadow-sm sm:rounded-bl-md sm:rounded-br-md sm:px-6">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            :href="route('list.index')"
                            wire:navigate
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            type="submit"
                            size="sm"
                            variant="primary"
                            class="my-1.5 bg-cyan-700 text-white hover:bg-cyan-600"
                        >
                            {{ __('Create List') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
