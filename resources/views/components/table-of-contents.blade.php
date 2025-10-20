@props(['title' => 'Table of Contents'])

<div class="hidden lg:block lg:w-64 lg:flex-shrink-0">
    <div
        class="sticky top-12 bg-gray-50 dark:bg-gray-800 rounded-lg mt-8 overflow-y-auto"
        style="max-height: calc(100vh - 5rem);"
    >
        <h3
            id="toc-heading"
            class="font-semibold text-gray-900 dark:text-gray-100 mb-4 py-4 px-2"
        >{{ $title }}</h3>
        <nav
            aria-labelledby="toc-heading"
            role="navigation"
            class="py-0 px-2 pb-4"
        >
            <ol
                class="space-y-2"
                role="list"
                style="list-style: none; padding: 0; margin: 0;"
            >
                {{ $slot }}
            </ol>
        </nav>
    </div>
</div>
