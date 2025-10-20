@props(['href', 'title' => null])

<li>
    <a
        href="{{ $href }}"
        class="nav-link block px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 rounded ml-4 transition-colors focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
        aria-describedby="{{ ltrim($href, '#') }}"
    >
        {{ $title ?? $slot }}
    </a>
</li>
