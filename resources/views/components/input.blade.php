@props(['disabled' => false])

<input
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge([
        'class' =>
            'rounded-md border-0 bg-gray-700 py-2 px-3 text-gray-300 ring-1 ring-inset ring-gray-700 placeholder:text-gray-400 focus:ring-inset focus:ring-gray-600 focus:bg-gray-200 focus:text-black focus:ring-0 sm:text-sm',
    ]) !!}
>
