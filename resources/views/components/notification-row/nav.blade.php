@props(['notification', 'presentation'])

<button
    type="button"
    wire:key="nav-notification-{{ $notification->id }}"
    wire:click="reviewNotification('{{ $notification->id }}')"
    x-on:click="notificationDropdownOpen = false"
    class="flex items-start gap-3 px-4 py-3 text-left hover:bg-gray-700/50 transition-colors"
>
    <div class="flex-shrink-0 mt-0.5">
        <div class="{{ $iconWrapperClasses() }}">
            <flux:icon :name="$presentation->iconName" class="w-4 h-4 text-white" />
        </div>
    </div>

    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-white truncate">
                {{ $primaryText() }}
            </span>
            <span
                class="text-xs text-gray-400 flex-shrink-0"
                title="{{ $tooltipTimestamp() }}"
            >
                {{ $shortRelativeTimestamp() }}
            </span>
        </div>

        <p class="text-xs text-gray-400 mt-0.5 truncate">
            {{ $summaryText() }}
        </p>

        @if ($presentation->preview !== null && $presentation->previewQuoted)
            <p class="text-xs text-gray-500 mt-1 italic truncate">
                "{{ Str::limit($presentation->preview, 50) }}"
            </p>
        @endif
    </div>
</button>
