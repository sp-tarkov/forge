<a
    href="/addon/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="0"
>
    @empty($result['thumbnail'])
        <div class="h-6 w-6 self-center flex items-center justify-center">
            <flux:icon.puzzle-piece class="size-5 text-gray-400 dark:text-gray-600" />
        </div>
    @else
        <img
            src="{{ $result['thumbnail'] }}"
            alt="{{ $result['name'] }}"
            class="h-6 w-6 self-center rounded object-cover"
        >
    @endempty
    <div class="grow flex flex-col">
        <p class="font-medium">{{ $result['name'] }}</p>
        @if (isset($result['mod_name']))
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Addon for: {{ $result['mod_name'] }}
            </p>
        @endif
    </div>
    @if (isset($result['is_detached']) && $result['is_detached'] && auth()->user()?->isModOrAdmin())
        <span
            class="ml-auto self-center inline-block bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wide text-nowrap"
        >
            Detached
        </span>
    @endif
</a>
