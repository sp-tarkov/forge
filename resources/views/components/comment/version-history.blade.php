@props(['comment'])

<flux:dropdown>
    <button
        type="button"
        class="ml-1 inline-flex items-center text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
    >
        <span>{{ __('edited') }}</span>
        <flux:icon.chevron-down
            variant="micro"
            class="size-3"
        />
    </button>

    <flux:menu>
        @foreach ($comment->versions as $version)
            <flux:menu.item wire:click="openVersionModal({{ $comment->id }}, {{ $version->id }})">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">v{{ $version->version_number }}</span>
                    <span>{{ $version->created_at->format('M j, Y g:i A') }}</span>
                    @if ($loop->first)
                        <flux:badge
                            size="sm"
                            color="green"
                        >{{ __('Current') }}</flux:badge>
                    @endif
                </div>
            </flux:menu.item>
        @endforeach
    </flux:menu>
</flux:dropdown>
