<div class="space-y-6">
    <flux:heading size="lg">Recent Activity</flux:heading>

    @if ($this->recentActivity->isEmpty())
        <div class="py-12 text-center">
            <div class="mb-4 flex justify-center">
                <flux:icon.clock class="h-12 w-12 text-gray-500" />
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
            <div class="absolute bottom-0 left-16 top-0 -z-10 w-px bg-gray-700"></div>

            <div class="space-y-8">
                @foreach ($this->recentActivity as $index => $event)
                    <div class="relative flex items-center space-x-8">
                        {{-- Timeline dot with icon - solid colored circle --}}
                        <flux:badge
                            variant=""
                            color="{{ $this->getEventColor($event) }}"
                            class="relative z-20 flex h-12 w-12 items-center justify-center rounded-full shadow-sm"
                        >
                            <flux:icon
                                name="{{ $this->getEventIcon($event) }}"
                                class="h-5 w-5 text-white"
                            />
                        </flux:badge>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-base font-semibold text-gray-100">
                                        {{ $event->event_display_name }}
                                    </h4>
                                    @if ($this->isEventPrivate($event))
                                        <flux:tooltip content="This activity is private and only visible to you.">
                                            <flux:icon.lock-closed class="h-4 w-4 text-gray-500" />
                                        </flux:tooltip>
                                    @endif
                                </div>
                                <time
                                    class="text-sm font-medium text-gray-400"
                                    datetime="{{ $event->created_at->toISOString() }}"
                                >
                                    {{ $event->created_at->diffForHumans() }}
                                </time>
                            </div>

                            {{-- Context information with background --}}
                            @if ($this->hasContext($event))
                                <div class="mb-1.5 mt-1 rounded-lg bg-gray-800/50 px-3 py-2">
                                    <div class="line-clamp-2 text-sm leading-relaxed text-gray-300">
                                        {{ $event->event_context }}
                                    </div>
                                </div>
                            @endif

                            {{-- Additional metadata --}}
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400">
                                @if ($event->ip && auth()->check() && auth()->user()->isAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.globe-alt class="h-3 w-3" />
                                        <span>{{ $event->ip }}</span>
                                    </span>
                                @endif
                                @if ($event->country_name && auth()->check() && auth()->user()->isAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.map-pin class="h-3 w-3" />
                                        <span>{{ $event->country_name }}</span>
                                    </span>
                                @endif
                                @if ($event->browser && auth()->check() && auth()->user()->isModOrAdmin())
                                    <span class="flex items-center space-x-1">
                                        <flux:icon.computer-desktop class="h-3 w-3" />
                                        <span>{{ $event->browser }}</span>
                                    </span>
                                @endif
                                <span class="flex items-center space-x-1">
                                    <flux:icon.calendar class="h-3 w-3" />
                                    <span>{{ $event->created_at->format('M j, Y g:i A') }}</span>
                                </span>
                                @if ($event->event_url)
                                    <a
                                        href="{{ $event->event_url }}"
                                        class="flex items-center space-x-1 text-white underline transition-colors hover:text-gray-200"
                                    >
                                        <flux:icon.arrow-top-right-on-square class="h-3 w-3" />
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
