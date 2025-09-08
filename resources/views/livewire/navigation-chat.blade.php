<div class="relative">
    <flux:dropdown align="end">
        <button class="relative rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out">
            <flux:icon.chat-bubble-left-ellipsis class="h-5 w-5" />
            @if($unreadCount > 0)
                <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
            <span class="sr-only">{{ __('Chat') }}</span>
        </button>

        <flux:menu class="min-w-[320px]">
            @if($conversations->count() > 0)
                <div class="py-2">
                    <div class="px-3 pb-2">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Recent Conversations') }}</p>
                    </div>
                    @foreach($conversations as $conversation)
                        @if($conversation->other_user)
                            <button
                                type="button"
                                wire:key="nav-conversation-{{ $conversation->id }}"
                                wire:click="navigateToConversation('{{ $conversation->hash_id }}')"
                                class="block w-full text-left px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <flux:avatar
                                        src="{{ $conversation->other_user->profile_photo_url }}"
                                        size="sm"
                                        circle
                                        color="auto"
                                        color:seed="{{ $conversation->other_user->id }}"
                                        class="flex-shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                    {{ $conversation->other_user->name }}
                                                </span>
                                                @if($conversation->unread_count > 0)
                                                    <flux:badge color="red" size="sm" class="text-[10px] px-1.5 py-0">{{ $conversation->unread_count }}</flux:badge>
                                                @endif
                                            </div>
                                            @if($conversation->lastMessage)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                                    {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                </div>
                                            @endif
                                        </div>
                                        @if($conversation->lastMessage)
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 truncate">
                                                @if($conversation->lastMessage->user_id === auth()->id())
                                                    <span class="text-gray-500">{{ __('You') }}:</span>
                                                @endif
                                                {{ Str::limit($conversation->lastMessage->content ?? '', 30) }}
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
                                                {{ __('No messages yet') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endif
                    @endforeach
                </div>

                <flux:menu.separator />

                <flux:menu.item x-on:click="$wire.showNewConversation = true" icon="plus" class="text-sm">
                    {{ __('New conversation') }}
                </flux:menu.item>

                <flux:menu.item href="{{ route('chat') }}" icon="arrow-right" class="text-sm" wire:navigate>
                    {{ __('View all conversations') }}
                </flux:menu.item>
            @else
                <div class="px-4 py-8 text-center">
                    <flux:icon name="chat-bubble-left-right" class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ __('No conversations yet') }}
                    </p>
                    <flux:button x-on:click="$wire.showNewConversation = true" size="sm" variant="primary">
                        {{ __('Start a conversation') }}
                    </flux:button>
                </div>
            @endif
        </flux:menu>
    </flux:dropdown>

    {{-- New Conversation Modal --}}
    <x-new-conversation-modal
        :show-modal="'showNewConversation'"
        :search-user="$searchUser"
        :search-results="$searchResults" />
</div>
