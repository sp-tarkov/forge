@props(['addon', 'wireKey' => null])

<div
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
    {{ $attributes->merge(['class' => 'mx-3 sm:mx-4 px-3 sm:px-4 py-1.5 rounded-md hover:bg-gray-900']) }}
>
    <div class="flex items-center gap-3">
        <div class="flex w-11 shrink-0 items-center justify-end sm:w-12">
            <a
                href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                wire:navigate
                class="shrink-0"
                aria-hidden="true"
                tabindex="-1"
            >
                @if ($addon->thumbnail)
                    <img
                        src="{{ $addon->thumbnailUrl }}"
                        alt=""
                        class="size-10 rounded object-cover"
                    >
                @else
                    <div class="flex size-10 items-center justify-center rounded bg-gray-800">
                        <flux:icon.puzzle-piece class="size-5 text-gray-400" />
                    </div>
                @endif
            </a>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <a
                    href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                    wire:navigate
                    class="truncate text-sm font-medium text-gray-100 hover:underline"
                >
                    {{ $addon->name }}
                </a>
                @if ($addon->latestVersion)
                    <span class="text-xs text-gray-400">
                        {{ $addon->latestVersion->version }}
                    </span>
                @endif
            </div>
            <div class="truncate text-xs text-gray-400">
                {{ __('by :owner', ['owner' => $addon->owner?->name ?? __('Unknown')]) }}
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            {{ $slot }}
        </div>
    </div>

    {{ $note ?? '' }}
</div>
