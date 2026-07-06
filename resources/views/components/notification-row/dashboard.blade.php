@props(['notification', 'presentation'])

<div
    wire:key="notification-{{ $notification->id }}"
    class="relative overflow-hidden rounded-xl border border-gray-700 bg-gray-800 shadow-sm transition-shadow duration-200 hover:shadow-md"
>
    @if (!$notification->read_at)
        <div class="absolute inset-y-0 left-0 w-1 bg-blue-500"></div>
    @endif

    <div
        wire:click="reviewNotification('{{ $notification->id }}')"
        class="{{ !$notification->read_at ? 'pl-5' : '' }} w-full cursor-pointer p-4 text-left"
    >
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
                <div class="{{ $iconWrapperClasses() }}">
                    <flux:icon
                        :name="$presentation->iconName"
                        :class="$iconClasses()"
                    />
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm">
                            @foreach ($presentation->headline as $segment)
                                <span class="{{ $segmentClasses($segment) }}">{{ $segment->text }}</span>
                            @endforeach
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-2">
                        @if (!$notification->read_at)
                            <button
                                type="button"
                                wire:click.stop="markAsRead('{{ $notification->id }}')"
                                class="text-xs text-blue-400 transition-colors hover:text-blue-300"
                            >
                                {{ __('Mark read') }}
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click.stop="deleteNotification('{{ $notification->id }}')"
                            wire:confirm="{{ __('Are you sure you want to delete this notification?') }}"
                            class="text-xs text-red-400 transition-colors hover:text-red-300"
                        >
                            {{ __('Delete') }}
                        </button>
                        <span
                            class="text-xs text-gray-500"
                            title="{{ $tooltipTimestamp() }}"
                        >
                            {{ $relativeTimestamp() }}
                        </span>
                    </div>
                </div>
                @if ($presentation->preview !== null)
                    <p
                        class="{{ $presentation->previewQuoted ? 'italic' : '' }} mt-2 line-clamp-2 text-sm text-gray-400">
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

    @if ($presentation->details !== [])
        <div class="{{ !$notification->read_at ? 'pl-5' : '' }} border-t border-gray-700 px-4 py-3">
            <ul class="space-y-2">
                @foreach ($presentation->details as $detail)
                    <li class="text-sm">
                        @if ($detail->url !== null)
                            <a
                                href="{{ $detail->url }}"
                                wire:navigate
                                class="font-medium text-blue-400 hover:underline"
                            >{{ $detail->label }}</a>
                        @else
                            <span class="font-medium text-white">{{ $detail->label }}</span>
                        @endif
                        @if ($detail->note !== null)
                            <span class="mt-0.5 block text-gray-400">{{ $detail->note }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
