<div
    x-data="{ open: false }"
    class="flex flex-1 justify-center px-2 lg:ml-6 lg:justify-end"
>
    <div class="w-full max-w-lg lg:max-w-md">
        <search
            x-trap.noreturn="$wire.query.length && open"
            x-on:click.away="open = false"
            x-on:keydown.escape.window="$wire.query = ''; open = false"
            class="relative group"
            role="search"
        >
            <flux:command>
                <flux:command.input
                    wire:model.live.debounce.250ms="query"
                    x-on:focus="open = true"
                    x-on:input="open = true"
                    placeholder="{{ __('Search everything') }}"
                    clearable
                />

                <div
                    x-cloak
                    x-show="$wire.query.length && open"
                    x-transition
                    class="absolute top-full z-20 mt-1 w-full max-w-2xl"
                >
                    <flux:command.items>
                        {{-- Loading State --}}
                        <div
                            wire:loading.delay
                            wire:target="query"
                            class="flex items-center justify-center gap-2 px-6 py-14"
                        >
                            <flux:icon.arrow-path class="size-5 animate-spin text-gray-400 dark:text-gray-500" />
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Searching...') }}</span>
                        </div>

                        {{-- Results Content --}}
                        <div
                            wire:loading.delay.remove
                            wire:target="query"
                        >
                            @if ($this->hasResults)
                                <h2 class="sr-only select-none">{{ __('Search Results') }}</h2>
                                <div
                                    class="max-h-96 scroll-py-2 overflow-y-auto"
                                    role="list"
                                    tabindex="-1"
                                >
                                    @foreach ($this->results as $type => $typeResults)
                                        @if ($typeResults->count())
                                            @php
                                                $visibilityProperty = 'is' . Str::ucfirst($type) . 'CatVisible';
                                                $isVisible = $this->$visibilityProperty;
                                            @endphp
                                            <h4
                                                wire:click="toggleTypeVisibility('{{ $type }}')"
                                                class="flex cursor-pointer select-none flex-row gap-1.5 bg-gray-100 px-4 py-2.5 text-[0.6875rem] font-semibold uppercase text-gray-700 dark:bg-gray-950 dark:text-gray-300"
                                            >
                                                <span>{{ Str::plural($type) }}</span>
                                                <flux:icon.chevron-right
                                                    class="size-4 transform transition-all duration-400 {{ $isVisible ? 'rotate-90' : '' }}"
                                                />
                                            </h4>
                                            <div
                                                class="max-h-0 transform divide-y divide-dashed divide-gray-200 overflow-hidden transition-all duration-400 dark:divide-gray-800 {{ $isVisible ? 'max-h-screen' : '' }}">
                                                @foreach ($typeResults as $hit)
                                                    <x-dynamic-component
                                                        :component="'global-search-result-' . Str::lower($type)"
                                                        :result="$hit"
                                                        link-class="group/global-search-link flex flex-row gap-3 py-1.5 px-4 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-800"
                                                    />
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @elseif (Str::length($this->query) > 0)
                                {{-- No Results --}}
                                <div class="px-6 py-14 text-center sm:px-14">
                                    <flux:icon.document-magnifying-glass
                                        class="mx-auto size-6 text-gray-400 dark:text-gray-500" />
                                    <p class="mt-4 text-sm text-gray-900 dark:text-gray-200">
                                        {{ __("We couldn't find any content with that query. Please try again.") }}
                                    </p>
                                </div>
                            @else
                                {{-- Initial Loading Placeholder --}}
                                <div class="flex items-center justify-center gap-2 px-6 py-14">
                                    <flux:icon.arrow-path class="size-5 animate-spin text-gray-400 dark:text-gray-500" />
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Searching...') }}</span>
                                </div>
                            @endif
                        </div>
                    </flux:command.items>
                </div>
            </flux:command>
        </search>
    </div>
</div>
