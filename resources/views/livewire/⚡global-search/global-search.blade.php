<div
    x-data="{ open: false }"
    class="flex flex-1 justify-center px-2 lg:ml-6 lg:justify-end"
>
    <div class="w-full max-w-lg lg:max-w-md">
        <label
            for="global-search"
            class="sr-only"
        >{{ __('Search') }}</label>
        <search
            x-trap.noreturn="$wire.query.length && open"
            x-on:click.away="open = false"
            x-on:keydown.down.prevent="$focus.wrap().next()"
            x-on:keydown.up.prevent="$focus.wrap().previous()"
            x-on:keydown.escape.window="$wire.query = ''; open = false"
            class="relative group"
            role="search"
        >
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <flux:icon.magnifying-glass
                    variant="mini"
                    class="size-5 text-gray-400"
                />
            </div>
            <input
                id="global-search"
                type="search"
                wire:model.live.debounce.250ms="query"
                x-on:focus="open = true"
                x-on:input="open = true"
                placeholder="{{ __('Search everything') }}"
                aria-controls="search-results"
                aria-label="{{ __('Search everything') }}"
                class="block w-full rounded-md border-0 bg-white py-1.5 pl-10 pr-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-700 dark:placeholder:text-gray-400 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 dark:[&::-webkit-search-cancel-button]:invert"
            />
            <div
                id="search-results"
                x-cloak
                x-show="$wire.query.length && open"
                x-transition
                aria-live="polite"
                class="absolute top-11 z-20 mx-auto w-full max-w-2xl transform overflow-hidden rounded-md border border-gray-300 bg-white shadow-2xl transition-opacity dark:border-gray-700 dark:bg-gray-900"
            >
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
                        {{-- Initial Loading Placeholder (shown while debounce is pending) --}}
                        <div class="flex items-center justify-center gap-2 px-6 py-14">
                            <flux:icon.arrow-path class="size-5 animate-spin text-gray-400 dark:text-gray-500" />
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Searching...') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </search>
    </div>
</div>