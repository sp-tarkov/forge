<a
    href="/addon/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    @empty($result['thumbnail'])
        <div class="flex size-8 shrink-0 items-center justify-center self-center rounded bg-gray-800">
            <flux:icon.puzzle-piece class="size-4 text-gray-500" />
        </div>
    @else
        <img
            src="{{ $result['thumbnail'] }}"
            alt="{{ $result['name'] }}"
            class="size-8 shrink-0 self-center rounded object-cover"
        >
    @endempty
    <div class="flex min-w-0 grow flex-col">
        <span class="truncate text-sm font-medium">{{ $result['name'] }}</span>
        @if (isset($result['mod_name']))
            <span class="text-xs text-gray-400">
                Addon for: {{ $result['mod_name'] }}
            </span>
        @endif
    </div>
    @if (isset($result['is_detached']) && $result['is_detached'] && auth()->user()?->isModOrAdmin())
        <flux:badge
            size="sm"
            color="yellow"
            class="ml-auto shrink-0"
        >Detached</flux:badge>
    @endif
</a>
