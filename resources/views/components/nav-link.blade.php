@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'rounded-md px-3 py-2 text-sm font-medium text-black dark:text-white bg-gray-100 dark:bg-gray-900 transition duration-150 ease-in-out'
        : 'rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-700 hover:text-black dark:hover:text-white transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
