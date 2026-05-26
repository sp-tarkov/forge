<x-slot:title>{{ __('Edit List - The Forge') }}</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.list-bullet class="w-5 h-5" />
        {{ __('Edit List') }}: {{ $modList->title }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        {{ __('List Details') }}
                        @if ($modList->is_default)
                            <flux:badge
                                icon="heart"
                                color="pink"
                                size="sm"
                            >{{ __('Favourites') }}</flux:badge>
                        @endif
                    </h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Update the details of your curated list. Changes will be visible immediately after saving.') }}
                    </p>
                    @unless ($modList->isFavourites())
                        <x-list.config-guidance class="mt-4" />
                    @endunless
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-8">
                            @unless ($modList->isFavourites())
                                <flux:field class="col-span-6">
                                    <flux:label badge="Optional">{{ __('Thumbnail') }}</flux:label>
                                    <flux:description>
                                        {{ __('Upload an image to represent this list. The image should be square, JPG, PNG, or WebP, and no larger than 2 MB.') }}
                                    </flux:description>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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
                                                    :aria-label="__('Remove selected thumbnail')"
                                                    wire:click="removeThumbnail"
                                                    type="button"
                                                />
                                            </div>
                                        @elseif ($modList->thumbnail)
                                            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-3">
                                                <img
                                                    src="{{ $modList->thumbnailUrl }}"
                                                    class="size-20 shrink-0 rounded-lg object-cover"
                                                    alt="{{ __('Current thumbnail') }}"
                                                >
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Current Thumbnail') }}</p>
                                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Upload a new image to replace, or delete the current one.') }}</p>
                                                </div>
                                                <flux:modal.trigger name="list-delete-thumbnail-{{ $modList->id }}">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="trash"
                                                        :aria-label="__('Delete current thumbnail')"
                                                        type="button"
                                                    />
                                                </flux:modal.trigger>
                                            </div>
                                        @endif
                                        <flux:file-upload wire:model="thumbnail" @class(['h-full', 'lg:col-span-2' => !$thumbnail && !$modList->thumbnail])>
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
                            @endunless

                            <flux:field class="col-span-6">
                                <flux:label>
                                    {{ __('Title') }}
                                    @if ($modList->is_default)
                                        <flux:badge
                                            color="red"
                                            icon="lock-closed"
                                            size="sm"
                                            class="ms-1.5"
                                        >{{ __('Locked') }}</flux:badge>
                                    @endif
                                </flux:label>
                                @if ($modList->is_default)
                                    <flux:description>
                                        {{ __('Your Favourites list has a fixed title and cannot be renamed.') }}
                                    </flux:description>
                                @endif
                                <flux:input
                                    wire:model="form.title"
                                    :disabled="$modList->is_default"
                                    required
                                    maxlength="{{ config('mod-lists.validation.title_max') }}"
                                />
                                <flux:error name="form.title" />
                            </flux:field>

                            @unless ($modList->isFavourites())
                                <flux:field class="col-span-6">
                                    <x-markdown-editor
                                        wire-model="form.description"
                                        name="form.description"
                                        :label="__('Description')"
                                        :description="__('Explain the list in detail. This will be displayed on the list page. Use markdown for formatting.')"
                                        rows="6"
                                        purify-config="description"
                                        maxlength="{{ config('mod-lists.validation.description_max') }}"
                                    />
                                </flux:field>
                            @endunless

                            <flux:field class="col-span-6">
                                <flux:label>
                                    {{ __('Visibility') }}
                                    @if ($modList->is_default)
                                        <flux:badge
                                            color="red"
                                            icon="lock-closed"
                                            size="sm"
                                            class="ms-1.5"
                                        >{{ __('Locked') }}</flux:badge>
                                    @endif
                                </flux:label>
                                @if ($modList->is_default)
                                    <flux:description>
                                        {{ __('Your Favourites list is always private and only visible to you.') }}
                                    </flux:description>
                                @endif
                                <flux:select
                                    variant="listbox"
                                    wire:model.live="form.visibility"
                                    :disabled="$modList->is_default"
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
                                <flux:select
                                    variant="listbox"
                                    searchable
                                    wire:model="form.spt_version_id"
                                >
                                    <flux:select.option value="">{{ __('Latest compatible version') }}</flux:select.option>
                                    @foreach ($form->availableSptVersions() as $version)
                                        <flux:select.option value="{{ $version->id }}">
                                            {{ $version->version }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="form.spt_version_id" />
                            </flux:field>

                            @unless ($modList->isFavourites())
                                <flux:field class="col-span-6">
                                    <flux:switch
                                        wire:model.live="form.comments_disabled"
                                        :label="__('Disable comments')"
                                        :description="$form->visibility === 'private' ? __('Private lists never show a comment thread.') : __('Hide the comment section from everyone, including you.')"
                                        :disabled="$form->visibility === 'private'"
                                    />
                                </flux:field>
                            @endunless
                        </div>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <div>
                            @if (! $modList->is_default)
                                <flux:modal.trigger name="list-delete-{{ $modList->id }}">
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                    >
                                        {{ __('Delete list') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                :href="route('list.show', ['listId' => $modList->id, 'slug' => $modList->slug])"
                                wire:navigate
                            >
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button
                                type="submit"
                                size="sm"
                                variant="primary"
                                class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                            >
                                {{ __('Save changes') }}
                            </flux:button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($modList->thumbnail)
        <flux:modal
            name="list-delete-thumbnail-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Delete current thumbnail?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('The list will fall back to a default placeholder until you upload a new image.') }}
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="deleteExistingThumbnail"
                    >
                        {{ __('Delete thumbnail') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @if (! $modList->is_default)
        <flux:modal
            name="list-delete-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Delete this list?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('":title" and all its items will be permanently deleted. This cannot be undone.', ['title' => $modList->title]) }}
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="delete"
                    >
                        {{ __('Delete list') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
