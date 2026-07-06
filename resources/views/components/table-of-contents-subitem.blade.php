@props(['href', 'title' => null])

<li>
    <a
        href="{{ $href }}"
        class="nav-link ml-4 block rounded px-3 py-1 text-sm text-gray-400 transition-colors hover:bg-gray-700 hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-gray-800"
        aria-describedby="{{ ltrim($href, '#') }}"
    >
        {{ $title ?? $slot }}
    </a>
</li>
