@props(['notification', 'presentation'])

<button
    type="button"
    wire:key="nav-notification-{{ $notification->id }}"
    wire:click="reviewNotification('{{ $notification->id }}')"
    x-on:click="notificationDropdownOpen = false"
    class="flex items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-700/50"
>
    <div class="mt-0.5 flex-shrink-0">
        <div class="{{ $iconWrapperClasses() }}">
            <flux:icon
                :name="$presentation->iconName"
                class="h-4 w-4 text-white"
            />
        </div>
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex items-center justify-between gap-2">
            <span class="truncate text-sm font-medium text-white">
                {{ $primaryText() }}
            </span>
            <span
                class="flex-shrink-0 text-xs text-gray-400"
                title="{{ $tooltipTimestamp() }}"
            >
                {{ $shortRelativeTimestamp() }}
            </span>
        </div>

        <p class="mt-0.5 truncate text-xs text-gray-400">
            {{ $summaryText() }}
        </p>

        @if ($presentation->preview !== null && $presentation->previewQuoted)
            <p class="mt-1 truncate text-xs italic text-gray-500">
                "{{ Str::limit($presentation->preview, 50) }}"
            </p>
        @endif
    </div>
</button>
