<a
    href="/mod/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    @empty($result['thumbnail'])
        <div class="flex size-8 shrink-0 items-center justify-center self-center rounded bg-gray-800">
            <flux:icon.cube-transparent class="size-4 text-gray-500" />
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
        @if (isset($result['downloads']))
            <span class="text-xs text-gray-400">
                {{ number_format($result['downloads']) }} downloads
            </span>
        @endif
    </div>
    <span
        class="badge-version {{ $result['latestVersionColorClass'] }} ml-auto inline-flex shrink-0 items-center text-nowrap rounded-md px-2 py-1 text-xs font-medium"
    >
        {{ $result['latestVersion'] }}
    </span>
</a>
