@props(['id', 'name', 'value'])

<div class="flex items-center text-base sm:text-sm">
    <input
        id="{{ $id }}"
        wire:model.live="{{ $name }}"
        value="{{ $value }}"
        type="radio"
        class="h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
    >
    <label
        for="{{ $id }}"
        class="ml-3 inline-flex min-w-0 cursor-pointer text-gray-300"
    >{{ $slot }}</label>
</div>
