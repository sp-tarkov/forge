<x-slot:title>{{ __('Edit List - The Forge') }}</x-slot>

<x-slot:header></x-slot>

<div class="mx-auto max-w-3xl px-2 sm:px-4 lg:px-8 py-6">
    <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Edit list') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Update the details of your curated list.') }}
                </p>
            </div>
            @if ($modList->is_default)
                <flux:badge
                    icon="heart"
                    color="pink"
                >{{ __('Favourites') }}</flux:badge>
            @endif
        </div>

        @unless ($modList->isFavourites())
            <x-list.config-guidance class="mb-6" />
        @endunless

        <form
            wire:submit="save"
            class="space-y-4"
        >
            @unless ($modList->isFavourites())
                <flux:field>
                    <flux:label>{{ __('Thumbnail') }}</flux:label>
                    <flux:description>
                        {{ __('Optionally upload an image to represent this list. The image should be square, JPG, PNG, or WebP, and no larger than 2 MB.') }}
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
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    :aria-label="__('Delete current thumbnail')"
                                    wire:click="deleteExistingThumbnail"
                                    wire:confirm="{{ __('Are you sure you want to delete the current thumbnail?') }}"
                                    type="button"
                                />
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

            <flux:input
                wire:model="form.title"
                :label="__('Title')"
                :readonly="$modList->is_default"
                :description="$modList->is_default ? __('Your Favourites list has a fixed title and cannot be renamed.') : null"
                required
                maxlength="{{ config('mod-lists.validation.title_max') }}"
            />

            @unless ($modList->isFavourites())
                <flux:textarea
                    wire:model="form.description"
                    :label="__('Description')"
                    :description="__('Markdown is supported.')"
                    rows="6"
                    maxlength="{{ config('mod-lists.validation.description_max') }}"
                />
            @endunless

            <flux:select
                wire:model="form.visibility"
                :label="__('Visibility')"
                :disabled="$modList->is_default"
                :description="$modList->is_default ? __('Your Favourites list is always private and only visible to you.') : null"
            >
                @foreach (\App\Enums\ListVisibility::cases() as $visibility)
                    <flux:select.option value="{{ $visibility->value }}">
                        {{ __($visibility->label()) }}: {{ __($visibility->description()) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:field>
                <flux:label badge="Optional">{{ __('Target SPT version') }}</flux:label>
                <flux:select wire:model="form.spt_version_id">
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
                <flux:switch
                    wire:model.live="form.comments_disabled"
                    :label="__('Disable comments')"
                    :description="$form->visibility === 'private' ? __('Private lists never show a comment thread.') : __('Hide the comment section from everyone, including you.')"
                    :disabled="$form->visibility === 'private'"
                />
            @endunless

            <div class="flex items-center justify-between gap-3 pt-2">
                <div>
                    @if (! $modList->is_default)
                        <flux:button
                            type="button"
                            variant="danger"
                            wire:click="delete"
                            wire:confirm="{{ __('Delete this list? This cannot be undone.') }}"
                        >
                            {{ __('Delete list') }}
                        </flux:button>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <flux:button
                        variant="ghost"
                        :href="route('list.show', ['listId' => $modList->id, 'slug' => $modList->slug])"
                        wire:navigate
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                    >
                        {{ __('Save changes') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</div>
