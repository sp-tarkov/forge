@props([
    'addon',
    'note' => null,
    'wireKey' => null,
])

<div
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
    {{ $attributes->merge(['class' => 'flex items-center gap-3 p-2 rounded-md bg-white dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900']) }}
>
    <a
        href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
        wire:navigate
        class="shrink-0"
    >
        @if ($addon->thumbnail)
            <img
                src="{{ $addon->thumbnailUrl }}"
                alt="{{ $addon->name }}"
                class="size-10 rounded object-cover"
            >
        @else
            <div class="size-10 rounded bg-gray-200 dark:bg-gray-800 flex items-center justify-center">
                <flux:icon.puzzle-piece class="size-5 text-gray-400" />
            </div>
        @endif
    </a>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 min-w-0">
            <a
                href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                wire:navigate
                class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline truncate"
            >
                {{ $addon->name }}
            </a>
            @if ($addon->latestVersion)
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $addon->latestVersion->version }}
                </span>
            @endif
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
            {{ __('by :owner', ['owner' => $addon->owner?->name ?? __('Unknown')]) }}
        </div>
        @if ($note)
            <div class="text-xs italic text-gray-600 dark:text-gray-400 mt-0.5 line-clamp-2">
                <flux:icon.chat-bubble-left class="inline size-3 mr-0.5 text-gray-400" />
                {{ $note }}
            </div>
        @endif
    </div>

    <div class="shrink-0 flex items-center gap-1">
        {{ $slot }}
    </div>
</div>
