<a
    href="/addon/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    @empty($result['thumbnail'])
        <div class="size-8 shrink-0 self-center flex items-center justify-center rounded bg-zinc-800">
            <flux:icon.puzzle-piece class="size-4 text-zinc-500" />
        </div>
    @else
        <img
            src="{{ $result['thumbnail'] }}"
            alt="{{ $result['name'] }}"
            class="size-8 shrink-0 self-center rounded object-cover"
        >
    @endempty
    <div class="grow flex flex-col min-w-0">
        <span class="text-sm font-medium truncate">{{ $result['name'] }}</span>
        @if (isset($result['mod_name']))
            <span class="text-xs text-zinc-400">
                Addon for: {{ $result['mod_name'] }}
            </span>
        @endif
    </div>
    @if (isset($result['is_detached']) && $result['is_detached'] && auth()->user()?->isModOrAdmin())
        <flux:badge size="sm" color="yellow" class="ml-auto shrink-0">Detached</flux:badge>
    @endif
</a>
