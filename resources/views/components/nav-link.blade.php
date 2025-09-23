@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'rounded-md px-3 py-2 text-sm font-medium text-gray-900 dark:text-white bg-gray-300/50 dark:bg-gray-700/50 backdrop-blur-sm transition duration-150 ease-in-out'
        : 'rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out';
@endphp

<a wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
