<div
    x-data="{
        query: $wire.entangle('query'),
        count: $wire.entangle('count'),
        show: false,
        isModCatVisible: $wire.isModCatVisible,
        isUserCatVisible: $wire.isUserCatVisible
    }"
    class="flex flex-1 justify-center px-2 lg:ml-6 lg:justify-end"
>
    <div class="w-full max-w-lg lg:max-w-md">
        <label for="global-search" class="sr-only">{{ __('Search') }}</label>
        <search
            x-trap.noreturn="query.length && show"
            @click.away="show = false"
            @keydown.down.prevent="$focus.wrap().next()"
            @keydown.up.prevent="$focus.wrap().previous()"
            @keydown.escape.window="$wire.query = '';"
            class="relative group"
            role="search"
        >
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                          d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                          clip-rule="evenodd" />
                </svg>
            </div>
            <input id="global-search"
                   type="search"
                   wire:model.live="query"
                   @focus="show = true"
                   placeholder="{{ __('Search everything') }}"
                   aria-controls="search-results"
                   aria-label="{{ __('Search everything') }}"
                   class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6"
            />
            <div id="search-results"
                 x-cloak
                 x-transition
                 x-show="query.length && show"
                 aria-live="polite"
                 class="absolute z-20 top-11 w-full mx-auto max-w-2xl transform overflow-hidden rounded-md bg-white dark:bg-gray-900 shadow-2xl border border-gray-300 dark:border-gray-700 transition-all"
            >
                <div x-cloak x-show="count">
                    <h2 class="sr-only select-none">{{ __('Search Results') }}</h2>
                    <div class="max-h-96 scroll-py-2 overflow-y-auto" role="list" tabindex="-1">
                        @foreach($result as $type => $results)
                            @if ($results->count())
                                <h4 x-on:click="is{{ Str::ucfirst($type) }}CatVisible = !is{{ Str::ucfirst($type) }}CatVisible; $wire.toggleTypeVisibility('{{ $type }}')" class="flex flex-row gap-1.5 py-2.5 px-4 text-[0.6875rem] font-semibold uppercase text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-950 select-none">
                                    <span>{{ Str::plural($type) }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 transition-all transform duration-400" :class="{'rotate-180': is{{ Str::ucfirst($type) }}CatVisible}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </h4>
                                <div class="divide-y divide-dashed divide-gray-200 dark:divide-gray-800 transition-all transform duration-400 max-h-0 overflow-hidden" :class="{'max-h-screen': is{{ Str::ucfirst($type) }}CatVisible}">
                                    @foreach($results as $hit)
                                        @component('components.global-search-result-' . Str::lower($type), [
                                            'result' => $hit,
                                            'linkClass' => 'group/global-search-link flex flex-row gap-3 py-1.5 px-4 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors duration-200 ease-in-out',
                                        ])
                                        @endcomponent
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div x-cloak x-show="count < 1" class="px-6 py-14 text-center sm:px-14">
                    <svg class="mx-auto h-6 w-6 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <p class="mt-4 text-sm text-gray-900 dark:text-gray-200">{{ __("We couldn't find any content with that query. Please try again.") }}</p>
                </div>
            </div>
        </search>
    </div>
</div>
