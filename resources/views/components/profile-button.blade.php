@props(['name'])

<div class="flex flex-col justify-stretch sm:flex-row">
    <button {{ $attributes->whereStartsWith('wire:') }} {{ $attributes->merge([
        'type' => 'button',
        'class' => 'inline-flex justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600',
    ]) }}>
        {{ $icon }}
        <span>{{ $slot }}</span>
    </button>
</div>
