<div id="search-results" aria-live="polite" class="{{ $showDropdown ? 'block' : 'hidden' }} absolute z-10 top-11 w-full mx-auto max-w-2xl transform overflow-hidden rounded-md bg-white dark:bg-gray-900 shadow-2xl border border-gray-300 dark:border-gray-700 transition-all">
    @if($showDropdown)
        <h2 class="sr-only">{{ __('Search Results') }}</h2>
        <div class="max-h-96 scroll-py-2 overflow-y-auto" role="list">
            @foreach($results['data'] as $typeName => $typeResults)
                @if($typeResults->count())
                    <h4 class="flex flex-row gap-1.5 py-2.5 px-4 text-[0.6875rem] font-semibold uppercase text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-950">
                        <span>{{ Str::plural($typeName) }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </h4>
                    <div class="divide-y divide-dashed divide-gray-200 dark:divide-gray-800">
                        @foreach($typeResults as $result)
                            @component('components.global-search-result-' . Str::lower($typeName), [
                                'result' => $result,
                                'linkClass' => 'group/global-search-link flex flex-row gap-3 py-1.5 px-4 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors duration-200 ease-in-out',
                            ])
                            @endcomponent
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    @if($noResults)
        <div class="px-6 py-14 text-center sm:px-14">
            <svg class="mx-auto h-6 w-6 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
            <p class="mt-4 text-sm text-gray-900 dark:text-gray-200">{{ __("We couldn't find any content with that query. Please try again.") }}</p>
        </div>
    @endif
</div>
