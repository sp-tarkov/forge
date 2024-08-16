@props(['id', 'name', 'value'])

<div class="flex items-center text-base sm:text-sm">
    <input id="{{ $id }}" wire:model.live="{{ $name }}" value="{{ $value }}" type="checkbox" class="cursor-pointer h-4 w-4 flex-shrink-0 rounded border-gray-300 text-gray-600 focus:ring-gray-500">
    <label for="{{ $id }}" class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300">{{ $slot }}</label>
</div>
