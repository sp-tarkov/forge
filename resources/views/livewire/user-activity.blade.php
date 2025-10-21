<div class="space-y-6">
    <flux:heading size="lg">Recent Activity</flux:heading>

    @if ($this->recentActivity->isEmpty())
        <div class="text-center py-12">
            <div class="flex justify-center mb-4">
                <flux:icon.clock class="w-12 h-12 text-gray-400 dark:text-gray-500" />
            </div>
            <flux:text
                variant="muted"
                class="text-lg"
            >No recent activity to display</flux:text>
            <flux:text
                variant="muted"
                size="sm"
                class="mt-1"
            >Activity will appear here as the user interacts with the platform</flux:text>
        </div>
    @else
        <div class="relative">
            {{-- Timeline line --}}
            <div class="absolute left-16 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700 -z-10"></div>

            <div class="space-y-8">
                @foreach ($this->recentActivity as $index => $event)
                    <div class="relative flex items-center space-x-8">
                        {{-- Timeline dot with icon - solid colored circle --}}
                        <flux:badge
                            variant=""
                            color="{{ $this->getEventColor($event) }}"
                            class="relative z-20 flex items-center justify-center w-12 h-12 rounded-full shadow-sm"
                        >
                            <flux:icon
                                name="{{ $this->getEventIcon($event) }}"
                                class="w-5 h-5 text-white"
                            />
                        </flux:badge>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $event->event_display_name }}
                                    </h4>
                                    @if ($this->isEventPrivate($event))
                                        <flux:tooltip content="This activity is private and only visible to you.">
                                            <flux:icon.lock-closed class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                        </flux:tooltip>
                                    @endif
                                </div>
                                <time
                                    class="text-sm text-gray-500 dark:text-gray-400 font-medium"
                                    datetime="{{ $event->created_at->toISOString() }}"
                                >
                                    {{ $event->created_at->diffForHumans() }}
                                </time>
                            </div>

                            {{-- Context information with background --}}
                            @if ($this->hasContext($event))
                                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2 mb-1.5 mt-1">
                                    <div class="text-sm text-gray-700 dark:text-gray-300 line-clamp-2 leading-relaxed">
                                        {{ $event->event_context }}
                                    </div>
                                </div>
                            @endif

                            {{-- Additional metadata --}}
                            <div
                                class="mt-0.5 flex items-center flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($event->ip && auth()->check() && auth()->user()->isAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.globe-alt class="w-3 h-3" />
                                        <span>{{ $event->ip }}</span>
                                    </span>
                                @endif
                                @if ($event->country_name && auth()->check() && auth()->user()->isAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.map-pin class="w-3 h-3" />
                                        <span>{{ $event->country_name }}</span>
                                    </span>
                                @endif
                                @if ($event->browser && auth()->check() && auth()->user()->isModOrAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.computer-desktop class="w-3 h-3" />
                                        <span>{{ $event->browser }}</span>
                                    </span>
                                @endif
                                <span class="flex items-center space-x-1">
                                    <flux:icon.calendar class="w-3 h-3" />
                                    <span>{{ $event->created_at->format('M j, Y g:i A') }}</span>
                                </span>
                                @if ($event->event_url)
                                    <a
                                        href="{{ $event->event_url }}"
                                        class="flex items-center space-x-1 text-white underline hover:text-gray-200 transition-colors"
                                    >
                                        <flux:icon.arrow-top-right-on-square class="w-3 h-3" />
                                        <span>View details</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
