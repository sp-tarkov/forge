<a
    href="/mod/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    @empty($result['thumbnail'])
        <div class="size-8 shrink-0 self-center flex items-center justify-center rounded bg-zinc-800">
            <flux:icon.cube-transparent class="size-4 text-zinc-500" />
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
        @if (isset($result['downloads']))
            <span class="text-xs text-zinc-400">
                {{ number_format($result['downloads']) }} downloads
            </span>
        @endif
    </div>
    <span class="ml-auto shrink-0 badge-version {{ $result['latestVersionColorClass'] }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
        {{ $result['latestVersion'] }}
    </span>
</a>
