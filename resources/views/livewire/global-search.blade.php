<div x-data="{ query: $wire.entangle('query'), showDropdown: $wire.entangle('showDropdown'), noResults: $wire.entangle('noResults') }"
     @keydown.esc.window="showDropdown = false"
     class="flex flex-1 justify-center px-2 lg:ml-6 lg:justify-end"
>
    <div class="w-full max-w-lg lg:max-w-md"
         x-trap="showDropdown && query.length"
         @click.away="showDropdown = false"
         @keydown.down.prevent="$focus.wrap().next()"
         @keydown.up.prevent="$focus.wrap().previous()"
    >
        <label for="search" class="sr-only">{{ __('Search') }}</label>
        <search class="relative group" role="search">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                </svg>
            </div>
            <input id="global-search"
                   type="search"
                   wire:model.live="query"
                   @focus="showDropdown = true"
                   @keydown.escape.window="$wire.query = ''; showDropdown = false; $wire.$refresh()"
                   placeholder="{{ __('Search') }}"
                   aria-controls="search-results"
                   :aria-expanded="showDropdown"
                   aria-label="{{ __('Search') }}"
                   class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6"
            />
            <x-global-search-results :showDropdown="$showDropdown" :noResults="$noResults" :results="$results" />
        </search>
    </div>
</div>
