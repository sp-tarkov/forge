<div
    wire:poll.10s="$refresh"
    id="notifications"
    class="bg-white dark:bg-gray-900 shadow-xl rounded-lg"
>
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('Notifications') }}
                @if ($unreadCount > 0)
                    <span class="ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        {{ $unreadCount }}
                    </span>
                @endif
            </h2>

            @if ($unreadCount > 0)
                <flux:button
                    wire:click="markAllAsRead"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Mark all as read') }}
                </flux:button>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="text-center py-8">
                <flux:icon
                    icon="bell"
                    class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600 mb-4"
                />
                <p class="text-gray-500 dark:text-gray-400">
                    {{ __('No notifications yet') }}
                </p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($notifications as $notification)
                    <div
                        class="flex items-start p-4 border border-gray-200 dark:border-gray-700 rounded-lg {{ $notification->read_at ? '' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <div class="flex-shrink-0 mr-3">
                            @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center">
                                    <flux:icon.exclamation-triangle class="w-5 h-5 text-white" />
                                </div>
                            @elseif($notification->type === 'App\Notifications\NewChatMessageNotification')
                                <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                                    <flux:icon.chat-bubble-left-right class="w-5 h-5 text-white" />
                                </div>
                            @else
                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                    <flux:icon.chat-bubble-left-ellipsis class="w-5 h-5 text-white" />
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            @if ($notification->type === 'App\Notifications\ReportSubmittedNotification')
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->data['reporter_name'] ?? 'Someone' }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                        title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                    >
                                        {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                    </p>
                                </div>

                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                    {{ __('reported') }} {{ $notification->data['reportable_title'] ?? 'content' }}
                                    {{ __('for') }}
                                    {{ Str::lower($notification->data['reason_label']) ?? 'unknown reason' }}
                                </p>

                                @if ($notification->data['reportable_excerpt'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 break-words">
                                        {{ $notification->data['reportable_excerpt'] }}
                                    </p>
                                @endif

                                @if ($notification->data['context'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 italic break-words">
                                        "{{ Str::words($notification->data['context'], 15, '...') }}"
                                    </p>
                                @endif

                                <div class="flex items-center space-x-3 mt-3">
                                    <a
                                        href="{{ $notification->data['reportable_url'] ?? '#' }}"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        {{ __('Review') }}
                                    </a>

                                    @if (!$notification->read_at)
                                        <button
                                            wire:click="markAsRead('{{ $notification->id }}')"
                                            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                                        >
                                            {{ __('Mark as read') }}
                                        </button>
                                    @endif

                                    <button
                                        wire:click="deleteNotification('{{ $notification->id }}')"
                                        class="text-sm text-red-500 hover:text-red-700"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            @elseif($notification->type === 'App\Notifications\NewChatMessageNotification')
                                {{-- Chat message notifications --}}
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->data['sender_name'] ?? 'Someone' }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                        title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                    >
                                        {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                    </p>
                                </div>

                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                    @if ($notification->data['message_count'] > 1)
                                        {{ __('sent you') }} {{ $notification->data['message_count'] }}
                                        {{ __('new messages') }}
                                    @else
                                        {{ __('sent you a new message') }}
                                    @endif
                                </p>

                                @if ($notification->data['latest_message_preview'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 italic break-words">
                                        "{{ $notification->data['latest_message_preview'] }}"
                                    </p>
                                @endif

                                <div class="flex items-center space-x-3 mt-3">
                                    <a
                                        href="{{ $notification->data['conversation_url'] ?? '#' }}"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        {{ __('View Conversation') }}
                                    </a>

                                    @if (!$notification->read_at)
                                        <button
                                            wire:click="markAsRead('{{ $notification->id }}')"
                                            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                                        >
                                            {{ __('Mark as read') }}
                                        </button>
                                    @endif

                                    <button
                                        wire:click="deleteNotification('{{ $notification->id }}')"
                                        class="text-sm text-red-500 hover:text-red-700"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            @else
                                {{-- Comment notifications --}}
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->data['commenter_name'] ?? 'Someone' }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                        title="{{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}"
                                    >
                                        {{ $notification->created_at->setTimezone(auth()->user()->timezone ?? 'UTC')->diffForHumans() }}
                                    </p>
                                </div>

                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                    {{ __('commented on') }}
                                    {{ $notification->data['commentable_title'] ?? 'your content' }}
                                </p>

                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 break-words">
                                    {{ Str::words($notification->data['comment_body'] ?? '', 15, '...') }}
                                </p>

                                <div class="flex items-center space-x-3 mt-3">
                                    <a
                                        href="{{ $notification->data['comment_url'] ?? '#' }}"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        {{ __('View Comment') }}
                                    </a>

                                    @if (!$notification->read_at)
                                        <button
                                            wire:click="markAsRead('{{ $notification->id }}')"
                                            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                                        >
                                            {{ __('Mark as read') }}
                                        </button>
                                    @endif

                                    <button
                                        wire:click="deleteNotification('{{ $notification->id }}')"
                                        class="text-sm text-red-500 hover:text-red-700"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links(data: ['scrollTo' => '#notifications']) }}
            </div>
        @endif
    </div>
</div>
