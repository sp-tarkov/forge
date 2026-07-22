<a
    href="/list/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    <div class="flex size-8 shrink-0 items-center justify-center self-center rounded bg-gray-800">
        <flux:icon.list-bullet class="size-4 text-gray-500" />
    </div>
    <div class="flex min-w-0 grow flex-col">
        <span class="truncate text-sm font-medium">{{ $result['title'] }}</span>
        @if (isset($result['owner_name']))
            <span class="text-xs text-gray-400">
                By {{ $result['owner_name'] }}
                @if (($result['item_count'] ?? 0) > 0)
                    &middot; {{ $result['item_count'] }} {{ Str::plural('mod', $result['item_count']) }}
                @endif
            </span>
        @endif
    </div>
    @if (!empty($result['spt_version']))
        <span
            class="ml-auto inline-flex shrink-0 items-center text-nowrap rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-gray-300"
        >
            {{ $result['spt_version'] }}
        </span>
    @endif
</a>
