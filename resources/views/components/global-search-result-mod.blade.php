<a
    href="/mod/{{ $result['id'] }}/{{ $result['slug'] }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="0"
>
    @empty($result['thumbnail'])
        <div class="h-6 w-6 self-center flex items-center justify-center">
            <flux:icon.cube-transparent class="w-5 h-5 text-gray-400 dark:text-gray-600" />
        </div>
    @else
        <img
            src="{{ $result['thumbnail'] }}"
            alt="{{ $result['name'] }}"
            class="h-6 w-6 self-center"
        >
    @endempty
    <div class="grow flex flex-col">
        <p class="font-medium">{{ $result['name'] }}</p>
        @if (isset($result['downloads']))
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($result['downloads']) }} downloads
            </p>
        @endif
    </div>
    <p
        class="ml-auto self-center badge-version {{ $result['latestVersionColorClass'] }} }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
        {{ $result['latestVersion'] }}
    </p>
</a>
