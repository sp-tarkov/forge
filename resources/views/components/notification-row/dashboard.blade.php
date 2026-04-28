@props(['notification', 'presentation'])

<div
    wire:key="notification-{{ $notification->id }}"
    class="relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden"
>
    @if (!$notification->read_at)
        <div class="absolute inset-y-0 left-0 w-1 bg-blue-500"></div>
    @endif

    <div
        wire:click="reviewNotification('{{ $notification->id }}')"
        class="w-full text-left p-4 {{ !$notification->read_at ? 'pl-5' : '' }} cursor-pointer"
    >
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
                <div class="{{ $iconWrapperClasses() }}">
                    <flux:icon :name="$presentation->iconName" :class="$iconClasses()" />
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm">
                            @foreach ($presentation->headline as $segment)
                                <span class="{{ $segmentClasses($segment) }}">{{ $segment->text }}</span>
                            @endforeach
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if (!$notification->read_at)
                            <button
                                type="button"
                                wire:click.stop="markAsRead('{{ $notification->id }}')"
                                class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                            >
                                {{ __('Mark read') }}
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click.stop="deleteNotification('{{ $notification->id }}')"
                            wire:confirm="{{ __('Are you sure you want to delete this notification?') }}"
                            class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                        >
                            {{ __('Delete') }}
                        </button>
                        <span
                            class="text-xs text-gray-400 dark:text-gray-500"
                            title="{{ $tooltipTimestamp() }}"
                        >
                            {{ $relativeTimestamp() }}
                        </span>
                    </div>
                </div>
                @if ($presentation->preview !== null)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 {{ $presentation->previewQuoted ? 'italic' : '' }} line-clamp-2">
                        @if ($presentation->previewQuoted)
                            "{{ $presentation->preview }}"
                        @else
                            {{ $presentation->preview }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
