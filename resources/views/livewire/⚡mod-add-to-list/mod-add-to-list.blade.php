<div>
    @auth
    <flux:modal
        name="mod-add-to-list-{{ $sourceType }}-{{ $sourceId }}"
        class="md:w-[520px]"
    >
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Add to a list') }}</flux:heading>
                <flux:subheading>
                    @if ($sourceType === 'mod')
                        {{ __('Choose an existing list or create a new one.') }}
                    @else
                        {{ __('Adding this addon will also add its parent mod if needed.') }}
                    @endif
                </flux:subheading>
            </div>

            @if ($showDependencyStep && $sourceType === 'mod')
                <div class="space-y-3">
                    <div class="text-sm">
                        {{ trans_choice('This mod requires :count other mod. Add it to the list too?|This mod requires :count other mods. Add them to the list too?', $this->suggestedDependencies->count(), ['count' => $this->suggestedDependencies->count()]) }}
                    </div>
                    <div class="space-y-2">
                        @foreach ($this->suggestedDependencies as $dep)
                            <label class="flex items-center gap-2 rounded-md p-2 bg-gray-50 dark:bg-gray-900">
                                <input
                                    type="checkbox"
                                    value="{{ $dep->id }}"
                                    wire:model="selectedDependencyIds"
                                    class="rounded border-gray-300 dark:border-gray-600"
                                >
                                <span class="text-sm">{{ $dep->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button
                            variant="ghost"
                            wire:click="cancelDependencyStep"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            variant="outline"
                            wire:click="$set('selectedDependencyIds', [])"
                        >
                            {{ __('Add mod only') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            wire:click="confirmDependencies"
                        >
                            {{ __('Add all') }}
                        </flux:button>
                    </div>
                </div>
            @elseif ($creatingNew)
                <div class="space-y-3">
                    <flux:input
                        wire:model="newTitle"
                        :label="__('New list title')"
                        required
                        maxlength="120"
                    />
                    <flux:select
                        wire:model="newVisibility"
                        :label="__('Visibility')"
                    >
                        @foreach (\App\Enums\ListVisibility::cases() as $visibility)
                            <flux:select.option value="{{ $visibility->value }}">
                                {{ __($visibility->label()) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button
                            variant="ghost"
                            wire:click="cancelCreatingNew"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            wire:click="createAndAdd"
                        >
                            {{ __('Create and add') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <flux:input
                    wire:model.live.debounce.200ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Filter your lists…')"
                    label:sr-only="{{ __('Filter lists') }}"
                />

                <flux:textarea
                    wire:model="note"
                    :label="__('Note (optional)')"
                    rows="2"
                    maxlength="280"
                />

                <div class="max-h-72 overflow-y-auto space-y-1">
                    @forelse ($this->userLists as $list)
                        <div
                            wire:key="add-to-list-row-{{ $list->id }}"
                            class="flex items-center justify-between gap-2 p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-900"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                @if ($list->isFavourites())
                                    <div class="shrink-0 size-8 rounded bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center">
                                        <flux:icon.heart class="size-4 text-rose-500" />
                                    </div>
                                @elseif ($list->thumbnail)
                                    <img
                                        src="{{ $list->thumbnailUrl }}"
                                        alt=""
                                        class="shrink-0 size-8 rounded object-cover"
                                    >
                                @else
                                    <div class="shrink-0 size-8 rounded bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <flux:icon
                                            :name="$list->visibility->icon()"
                                            class="size-4 text-gray-500"
                                        />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="text-sm font-medium truncate">
                                        {{ $list->title }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $list->items_count }} {{ __(Str::plural('item', $list->items_count)) }}
                                    </div>
                                </div>
                            </div>
                            @if ($this->membershipFor($list->id))
                                <flux:button
                                    size="sm"
                                    variant="outline"
                                    wire:click="removeFromList({{ $list->id }})"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            @else
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="addToList({{ $list->id }})"
                                >
                                    {{ __('Add') }}
                                </flux:button>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-center text-gray-500 dark:text-gray-400 p-4">
                            {{ __('No matching lists.') }}
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-800 pt-3">
                    <flux:button
                        variant="ghost"
                        icon="plus"
                        wire:click="startCreatingNew"
                    >
                        {{ __('Create new list') }}
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="outline">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            @endif
        </div>
    </flux:modal>
    @endauth
</div>
