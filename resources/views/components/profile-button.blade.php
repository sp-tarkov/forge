@props(['name'])

<div class="flex flex-col justify-stretch sm:flex-row">
    <button
        {{ $attributes->whereStartsWith('wire:') }}
        {{ $attributes->merge([
            'type' => 'button',
            'class' =>
                'inline-flex justify-center rounded-md bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-100 shadow-xs ring-1 ring-inset ring-gray-600 hover:bg-gray-600',
        ]) }}
    >
        {{ $icon }}
        <span>{{ $slot }}</span>
    </button>
</div>
