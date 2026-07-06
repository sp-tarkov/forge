@props(['title' => 'Table of Contents'])

<div class="hidden lg:block lg:w-64 lg:flex-shrink-0">
    <div
        class="sticky top-12 mt-8 overflow-y-auto rounded-lg bg-gray-800"
        style="max-height: calc(100vh - 5rem);"
    >
        <h3
            id="toc-heading"
            class="mb-4 px-2 py-4 font-semibold text-gray-100"
        >{{ $title }}</h3>
        <nav
            aria-labelledby="toc-heading"
            role="navigation"
            class="px-2 py-0 pb-4"
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
