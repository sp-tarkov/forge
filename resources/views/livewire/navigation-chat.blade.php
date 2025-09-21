<div>
    <div
        x-data="{ chatDropdownOpen: false }"
        x-on:keydown.esc.window="chatDropdownOpen = false"
        x-on:close-chat-dropdown.window="chatDropdownOpen = false"
        class="relative"
    >
    <button
        type="button"
        x-on:click="chatDropdownOpen = !chatDropdownOpen"
        class="relative rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out"
        :aria-expanded="chatDropdownOpen"
        aria-haspopup="true"
    >
        <flux:icon.chat-bubble-left-ellipsis class="h-5 w-5" />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
        <span class="sr-only">{{ __('Chat') }}</span>
    </button>

    <div
        x-cloak
        x-show="chatDropdownOpen"
        x-transition
        x-on:click.outside="chatDropdownOpen = false"
        class="absolute top-11 right-0 z-[100] flex w-full min-w-[20rem] flex-col overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
        role="menu"
    >
            @if($conversations->count() > 0)
                <div class="flex flex-col divide-y divide-slate-300 dark:divide-gray-700">
                    <div class="flex flex-col py-2">
                        <div class="px-3 pb-2">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ __('Recent Conversations') }}</p>
                        </div>
                        @foreach($conversations as $conversation)
                            @if($conversation->other_user)
                                <button
                                    type="button"
                                    wire:key="nav-conversation-{{ $conversation->id }}"
                                    wire:click="navigateToConversation('{{ $conversation->hash_id }}')"
                                    x-on:click="chatDropdownOpen = false"
                                    class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-shrink-0">
                                            <flux:avatar
                                                src="{{ $conversation->other_user->profile_photo_url }}"
                                                size="sm"
                                                circle
                                                color="auto"
                                                color:seed="{{ $conversation->other_user->id }}"
                                            />
                                            @if(isset($onlineUsers[$conversation->other_user->id]))
                                                <span class="absolute bottom-0 right-0 block h-2 w-2 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"></span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-sm text-slate-900 dark:text-slate-100 truncate">
                                                        {{ $conversation->other_user->name }}
                                                    </span>
                                                    @if($conversation->unread_count > 0)
                                                        <flux:badge color="red" size="sm" class="text-[10px] px-1.5 py-0">{{ $conversation->unread_count }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if($conversation->lastMessage)
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 flex-shrink-0">
                                                        {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if($conversation->lastMessage)
                                                <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
                                                    {{ Str::limit($conversation->lastMessage->content ?? '', 40) }}
                                                </div>
                                            @else
                                                <div class="text-xs text-slate-500 dark:text-slate-500 mt-0.5">
                                                    {{ __('No messages yet') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex flex-col py-1.5">
                        <button
                            x-on:click="$wire.showNewConversation = true; chatDropdownOpen = false"
                            class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.plus class="w-4 h-4" />
                            {{ __('New conversation') }}
                        </button>
                        <a
                            href="{{ route('chat') }}"
                            wire:navigate
                            x-on:click="chatDropdownOpen = false"
                            class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.arrow-right class="w-4 h-4" />
                            {{ __('View all conversations') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="px-4 py-8 text-center">
                    <flux:icon name="chat-bubble-left-right" class="w-12 h-12 mx-auto text-slate-400 dark:text-slate-600 mb-3" />
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                        {{ __('No conversations yet') }}
                    </p>
                    <flux:button x-on:click="$wire.showNewConversation = true; chatDropdownOpen = false" size="sm" variant="primary">
                        {{ __('Start a conversation') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    {{-- New Conversation Modal --}}
    <x-new-conversation-modal
        :show-modal="'showNewConversation'"
        :search-user="$searchUser"
        :search-results="$searchResults" />

    {{-- JavaScript for presence channel --}}
    @script
        <script>
            const offlineDeboune = 2000;
            const offlineTimers = new Map();
            let navPresenceChannelJoined = false;
            let userPrivateChannel = null;

            // Join the global presence channel for tracking online users
            $wire.on('join-presence-channel', () => {
                if (!window.Echo) return;

                const existingChannel = window.Echo.connector.channels['presence-presence.online'];
                if (existingChannel?.subscription?.members) {
                    // Already joined, get current members
                    const users = [];
                    existingChannel.subscription.members.each((member) => {
                        users.push({
                            id: member.id,
                            name: member.info.name,
                            profile_photo_url: member.info.profile_photo_url
                        });
                    });
                    $wire.handleUsersHere(users);
                } else if (!navPresenceChannelJoined) {
                    // Not joined yet, join the channel
                    navPresenceChannelJoined = true;
                    window.Echo.join('presence.online')
                        .here((users) => $wire.handleUsersHere(users))
                        .joining((user) => {
                            // Clear any pending offline timer
                            if (offlineTimers.has(user.id)) {
                                clearTimeout(offlineTimers.get(user.id));
                                offlineTimers.delete(user.id);
                            }
                            $wire.handleUserJoining(user);
                        })
                        .leaving((user) => $wire.handleUserLeaving(user))
                        .error((error) => console.error('Nav - Presence channel error:', error));
                }
            });

            // Handle debounced offline check
            $wire.on('debounce-user-offline', ({ userId }) => {
                // Clear existing timer if any
                if (offlineTimers.has(userId)) {
                    clearTimeout(offlineTimers.get(userId));
                }

                // Set new timer
                const timerId = setTimeout(() => {
                    $wire.dispatch('check-user-offline', { userId });
                    offlineTimers.delete(userId);
                }, offlineDeboune);

                offlineTimers.set(userId, timerId);
            });

            // Set up private channel for user-specific events
            $wire.on('set-user-id', ({ userId }) => {
                if (!window.Echo || !userId) return;

                // Clean up existing channel
                if (userPrivateChannel) {
                    window.Echo.leave(`user.${userPrivateChannel}`);
                }

                userPrivateChannel = userId;
                window.Echo.private(`user.${userId}`)
                    .listen('MessageSent', (e) => $wire.handleNewMessage(e))
                    .listen('ConversationUpdated', (e) => $wire.handleConversationUpdated(e));
            });
        </script>
    @endscript
</div>
