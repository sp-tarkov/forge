@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'block rounded-md px-3 py-2 text-base font-medium text-gray-700 dark:text-gray-100 bg-gray-100 dark:bg-gray-900 transition duration-150 ease-in-out'
        : 'block rounded-md px-3 py-2 text-base font-medium text-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-800 dark:hover:text-white transition duration-150 ease-in-out';
@endphp

<a wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
