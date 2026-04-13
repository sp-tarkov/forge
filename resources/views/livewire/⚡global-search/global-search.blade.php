<div
    class="flex flex-1 justify-end px-2 lg:ml-6"
    x-data="{ open: false }"
    x-on:keydown.cmd.k.window.prevent="open = true"
    x-on:keydown.ctrl.k.window.prevent="open = true"
>
    <div class="relative z-10 w-40 sm:w-48">
        {{-- Trigger Button --}}
        <flux:input
            as="button"
            size="sm"
            placeholder="{{ __('Search...') }}"
            icon="magnifying-glass"
            kbd="⌘K"
            x-on:click="open = true"
            class="!bg-gray-800 !border-gray-700"
        />
    </div>

    {{-- Search Modal --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-trap.noreturn.noscroll="open"
            x-on:keydown.escape.window="open = false; $wire.set('query', '')"
            class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]"
            style="display: none;"
        >
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/70"
                x-on:click="open = false; $wire.set('query', '')"
            ></div>

            {{-- Panel --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                x-on:keydown.down.prevent="
                    if (document.activeElement === $refs.searchInput) {
                        $refs.resultsList?.querySelector('[tabindex=&quot;0&quot;]')?.focus();
                    } else if ($refs.resultsList) {
                        $focus.within($refs.resultsList).next();
                    }
                "
                x-on:keydown.up.prevent="
                    if ($refs.resultsList) {
                        const first = $refs.resultsList.querySelector('[tabindex=&quot;0&quot;]');
                        if (document.activeElement === first) {
                            $refs.searchInput.focus();
                        } else {
                            $focus.within($refs.resultsList).previous();
                        }
                    }
                "
                x-init="$watch('open', value => { if (value) $nextTick(() => $refs.searchInput.focus()) })"
                class="relative z-10 w-full max-w-[36rem] rounded-xl bg-gray-900 shadow-2xl ring-1 ring-gray-700/50 overflow-hidden"
            >
                {{-- Search Input --}}
                <div class="flex items-center gap-2 border-b border-gray-700/40 px-4">
                    <flux:icon.magnifying-glass class="size-5 shrink-0 text-gray-400" />
                    <input
                        x-ref="searchInput"
                        id="global-search"
                        type="search"
                        wire:model.live.debounce.250ms="query"
                        placeholder="{{ __('Search everything...') }}"
                        class="min-w-0 flex-1 border-0 bg-transparent py-3.5 text-sm text-gray-100 placeholder:text-gray-500 focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:hidden"
                        autocomplete="off"
                        x-on:keydown.enter.prevent="$refs.resultsList?.querySelector('a[role=listitem]')?.click()"
                    />
                    @if (Str::length($this->query) > 0)
                        <button
                            type="button"
                            wire:click="$set('query', '')"
                            tabindex="-1"
                            class="shrink-0 rounded p-1 text-gray-500 hover:text-gray-300 transition-colors"
                        >
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    @endif
                    <kbd class="shrink-0 rounded border border-gray-600 bg-gray-800 px-1.5 py-0.5 text-[10px] font-medium text-gray-400">ESC</kbd>
                </div>

                {{-- Empty State --}}
                @if (Str::length($this->query) === 0)
                    <div class="px-6 py-14 text-center">
                        <flux:icon.magnifying-glass class="mx-auto size-6 text-gray-500" />
                        <p class="mt-4 text-sm text-gray-400">{{ __('Start typing to search mods, addons, and users.') }}</p>
                    </div>
                @endif

                {{-- Results --}}
                @if (Str::length($this->query) > 0)
                    <div x-ref="resultsList" class="max-h-[60vh] overflow-y-auto">
                        {{-- Loading Skeleton --}}
                        <div
                            wire:loading.delay
                            wire:target="query"
                            class="p-2"
                        >
                            <flux:skeleton.group animate="shimmer">
                                @foreach (range(1, 6) as $i)
                                    <div class="flex items-center gap-3 px-2 py-2.5">
                                        <flux:skeleton class="size-8 rounded shrink-0" />
                                        <div class="flex-1 space-y-1.5">
                                            <flux:skeleton.line class="w-[{{ rand(40, 70) }}%]" />
                                            <flux:skeleton.line class="w-[{{ rand(20, 40) }}%]" />
                                        </div>
                                        <flux:skeleton class="h-6 w-16 rounded-md shrink-0" />
                                    </div>
                                @endforeach
                            </flux:skeleton.group>
                        </div>

                        {{-- Results Content --}}
                        <div
                            wire:loading.delay.remove
                            wire:target="query"
                        >
                            @if ($this->hasResults)
                                @foreach ($this->results as $type => $typeResults)
                                    @if ($typeResults->count())
                                        @php
                                            $visibilityProperty = 'is' . Str::ucfirst($type) . 'CatVisible';
                                            $isVisible = $this->$visibilityProperty;
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="toggleTypeVisibility('{{ $type }}')"
                                            tabindex="0"
                                            x-on:keydown.enter.prevent="$el.click()"
                                            x-on:keydown.space.prevent="$el.click()"
                                            class="flex w-full cursor-pointer select-none flex-row items-center gap-1.5 border-t border-gray-700/40 bg-gray-800/80 px-4 py-2 text-[0.6875rem] font-semibold uppercase tracking-wide text-gray-400 hover:text-gray-300 focus:bg-gray-700/60 focus:text-gray-300 focus:outline-none transition-colors"
                                        >
                                            <span class="flex items-center gap-1.5">
                                                {{ $typeResults->count() }} {{ $typeResults->count() === 1 ? Str::upper($type) : Str::upper(Str::plural($type)) }}
                                            </span>
                                            <flux:icon.chevron-right
                                                class="size-3 transform transition-transform duration-200 {{ $isVisible ? 'rotate-90' : '' }}"
                                            />
                                        </button>
                                        <div
                                            class="divide-y divide-gray-700/30 overflow-hidden transition-all duration-200 {{ $isVisible ? 'max-h-screen' : 'max-h-0' }}"
                                            @if (!$isVisible) inert @endif
                                        >
                                            @foreach ($typeResults as $hit)
                                                <x-dynamic-component
                                                    :component="'global-search-result-' . Str::lower($type)"
                                                    :result="$hit"
                                                    link-class="flex flex-row items-center gap-3 py-2.5 px-4 text-gray-200 hover:bg-gray-700/60 focus:bg-gray-700/60 focus:outline-none transition-colors"
                                                />
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                {{-- No Results --}}
                                <div class="px-6 py-14 text-center">
                                    <flux:icon.document-magnifying-glass class="mx-auto size-6 text-gray-500" />
                                    <p class="mt-4 text-sm text-gray-400">
                                        {{ __("We couldn't find any content with that query. Please try again.") }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
