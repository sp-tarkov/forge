<a href="/mod/{{ $result['id'] }}/{{ $result['slug'] }}" class="{{ $linkClass }}" role="listitem" tabindex="0" class="flex flex-col">
    @empty ($result['thumbnail'])
        <img src="https://placehold.co/450x450/31343C/EEE?font=source-sans-pro&text={{ urlencode($result['name']) }}" alt="{{ $result['name'] }}" class="h-6 w-6 self-center border border-gray-700 group-hover/global-search-link:border-gray-600">
    @else
        <img src="{{ $result['thumbnail'] }}" alt="{{ $result['name'] }}" class="h-6 w-6 self-center">
    @endempty
    <p class="grow">{{ $result['name'] }}</p>
    <p class="ml-auto self-center badge-version {{ $result['latestVersionColorClass'] }} }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
        {{ $result['latestVersion'] }}
    </p>
</a>
