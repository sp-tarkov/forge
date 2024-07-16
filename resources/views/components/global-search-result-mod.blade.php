<a href="/mod/{{ $result['id'] }}/{{ $result['slug'] }}" class="{{ $linkClass }}" role="listitem" tabindex="0" class="flex flex-col">
    @if(empty($result->thumbnail))
        <img src="https://placehold.co/450x450/EEE/31343C?font=source-sans-pro&text={{ $result['name'] }}" alt="{{ $result['name'] }}" class="block dark:hidden h-6 w-6 self-center border border-gray-200 group-hover/global-search-link:border-gray-400">
        <img src="https://placehold.co/450x450/31343C/EEE?font=source-sans-pro&text={{ $result['name'] }}" alt="{{ $result['name'] }}" class="hidden dark:block h-6 w-6 self-center border border-gray-700 group-hover/global-search-link:border-gray-600">
    @else
        <img src="{{ Storage::url($result['thumbnail']) }}" alt="{{ $result['name'] }}" class="h-6 w-6 self-center">
    @endif
    <p class="flex-grow">{{ $result['name'] }}</p>
    <p class="ml-auto self-center badge-version {{ $result['latestSptVersionColorClass'] }} }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
        {{ $result['latestSptVersion'] }}
    </p>
</a>
