<div
    x-data="{ listDropdownOpen: false }"
    x-on:keydown.esc.window="listDropdownOpen = false"
    x-on:close-list-dropdown.window="listDropdownOpen = false"
    class="relative flex items-center"
>
    @auth
        <flux:button
            type="button"
            size="sm"
            square="true"
            icon="heart"
            :icon:variant="$this->isOnAnyList ? 'solid' : 'mini'"
            icon:class="size-5"
            x-on:click="listDropdownOpen = !listDropdownOpen"
            ::aria-expanded="listDropdownOpen"
            aria-haspopup="true"
            :title="$this->isOnAnyList ? __('Saved to one of your lists') : __('Save to a list')"
            :aria-label="$this->isOnAnyList ? __('Saved to one of your lists') : __('Save to a list')"
            :class="$this->isOnAnyList ? '[&_svg]:text-red-500' : ''"
        />

        <div
            x-cloak
            x-show="listDropdownOpen"
            x-transition
            x-on:click.outside="listDropdownOpen = false"
            class="absolute top-11 right-0 z-[100] flex w-72 flex-col overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
            role="menu"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-300 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ __('Save to List') }}
                </h3>
            </div>

            <div class="flex flex-col divide-y divide-slate-300 dark:divide-gray-700">
                @if ($this->userLists->isNotEmpty())
                    <div class="flex flex-col py-2 max-h-72 overflow-y-auto">
                        @foreach ($this->userLists as $list)
                            @php($contains = $this->listIdsContainingMod->contains($list->id))
                            <button
                                type="button"
                                wire:key="mod-list-row-{{ $list->id }}"
                                wire:click="toggleList({{ $list->id }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleList({{ $list->id }})"
                                class="group flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden disabled:opacity-60 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                                role="menuitem"
                            >
                                @if ($list->isFavourites())
                                    <div class="shrink-0 size-8 rounded bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center">
                                        <flux:icon.heart
                                            variant="micro"
                                            class="size-4 text-rose-500"
                                        />
                                    </div>
                                @elseif ($list->thumbnail)
                                    <img
                                        src="{{ $list->thumbnailUrl }}"
                                        alt=""
                                        class="shrink-0 size-8 rounded object-cover"
                                    >
                                @else
                                    <div class="shrink-0 size-8 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <flux:icon
                                            :name="$list->visibility->icon()"
                                            variant="micro"
                                            class="size-4 text-gray-500"
                                        />
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-sm truncate">{{ $list->title }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $list->items_count }} {{ __(Str::plural('item', $list->items_count)) }}
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    @if ($contains)
                                        <flux:icon.check-circle
                                            variant="mini"
                                            class="size-5 text-lime-600 dark:text-lime-400"
                                        />
                                    @else
                                        <flux:icon.plus-circle
                                            variant="mini"
                                            class="size-5 text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-6 text-center text-sm text-slate-600 dark:text-slate-400">
                        {{ __('You have no lists yet.') }}
                    </div>
                @endif

                <div class="flex flex-col py-1.5">
                    <a
                        href="{{ route('list.create') }}"
                        wire:navigate
                        x-on:click="listDropdownOpen = false"
                        class="flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-gray-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                        role="menuitem"
                    >
                        <flux:icon.plus
                            variant="micro"
                            class="size-4"
                        />
                        {{ __('Create New List') }}
                    </a>
                    <a
                        href="{{ route('list.index') }}"
                        wire:navigate
                        x-on:click="listDropdownOpen = false"
                        class="flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-gray-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                        role="menuitem"
                    >
                        <flux:icon.arrow-right
                            variant="micro"
                            class="size-4"
                        />
                        {{ __('View All Lists') }}
                    </a>
                </div>
            </div>
        </div>

        @if ($showDependencyModal)
            <flux:modal
                wire:model.self="showDependencyModal"
                class="md:w-[480px]"
            >
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Add dependencies?') }}</flux:heading>
                        <flux:subheading>
                            {{ __('This mod requires the following to work. Add them to the list as well?') }}
                        </flux:subheading>
                    </div>

                    <ul class="divide-y divide-gray-200 dark:divide-gray-800 rounded-md border border-gray-200 dark:border-gray-800">
                        @foreach ($this->pendingDependencies as $dep)
                            <li class="flex items-center gap-3 px-3 py-2">
                                @if ($dep->thumbnail)
                                    <img
                                        src="{{ $dep->thumbnailUrl }}"
                                        alt=""
                                        class="shrink-0 size-8 rounded object-cover"
                                    >
                                @else
                                    <div class="shrink-0 size-8 rounded bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <flux:icon.cube-transparent class="size-4 text-gray-500" />
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium truncate text-gray-900 dark:text-gray-100">
                                        {{ $dep->name }}
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button
                            variant="ghost"
                            wire:click="addIgnoringDependencies"
                        >
                            {{ __('Ignore dependencies') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            wire:click="addWithDependencies"
                        >
                            {{ __('Add dependencies') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @else
        <flux:tooltip :content="__('Log in to save to a list')">
            <flux:button
                :href="route('login')"
                variant="subtle"
                :size="$size"
                square
                icon="heart"
                class="text-gray-400"
                aria-label="{{ __('Log in to save to a list') }}"
            />
        </flux:tooltip>
    @endauth
</div>
