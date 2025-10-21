@props(['disabled' => false])

<input
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge([
        'class' =>
            'rounded-md border-0 bg-white dark:bg-gray-700 py-2 px-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm',
    ]) !!}
>
