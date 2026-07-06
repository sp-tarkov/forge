<div>
    <div
        x-data="{ chatDropdownOpen: false }"
        x-on:keydown.esc.window="chatDropdownOpen = false"
        x-on:close-chat-dropdown.window="chatDropdownOpen = false"
        class="relative"
    >
        <flux:button
            variant="subtle"
            square="true"
            class="group relative"
            x-on:click="chatDropdownOpen = !chatDropdownOpen"
            x-bind:aria-expanded="chatDropdownOpen"
            aria-haspopup="true"
            aria-label="{{ __('Chat') }}"
        >
            <flux:icon.chat-bubble-left-ellipsis
                variant="mini"
                class="text-white"
            />
            @if ($this->unreadCount > 0)
                <span
                    class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
                >
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <div
            x-cloak
            x-show="chatDropdownOpen"
            x-transition
            x-on:click.outside="chatDropdownOpen = false"
            class="absolute top-11 right-0 z-[100] flex w-full min-w-[20rem] flex-col overflow-hidden rounded-xl border border-gray-700 bg-gray-800"
            role="menu"
        >
            @if ($this->conversations->count() > 0)
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                    <h3 class="text-sm font-semibold text-white">
                        {{ __('Recent Conversations') }}
                    </h3>
                </div>

                <div class="flex flex-col divide-y divide-gray-700">
                    <div class="flex flex-col py-2">
                        @foreach ($this->conversations as $conversation)
                            @if ($conversation->other_user)
                                <button
                                    type="button"
                                    wire:key="nav-conversation-{{ $conversation->id }}"
                                    wire:click="navigateToConversation('{{ $conversation->hash_id }}')"
                                    x-on:click="chatDropdownOpen = false"
                                    class="block w-full text-left px-4 py-2 text-sm focus-visible:outline-hidden text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
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
                                            @if (isset($onlineUsers[$conversation->other_user->id]))
                                                <span
                                                    class="absolute bottom-0 right-0 block h-2 w-2 rounded-full ring-2 ring-gray-900 bg-green-400"
                                                ></span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-sm truncate">
                                                        <x-user-name :user="$conversation->other_user" />
                                                    </span>
                                                    @if ($conversation->unread_count > 0)
                                                        <flux:badge
                                                            color="red"
                                                            size="sm"
                                                            class="text-[10px] px-1.5 py-0"
                                                        >{{ $conversation->unread_count }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if ($conversation->lastMessage)
                                                    <div
                                                        class="text-xs text-slate-400 flex-shrink-0">
                                                        {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($conversation->lastMessage)
                                                <div class="text-xs text-slate-400 mt-0.5 truncate">
                                                    {{ Str::limit($conversation->lastMessage->content ?? '', 40) }}
                                                </div>
                                            @else
                                                <div class="text-xs text-slate-500 mt-0.5">
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
                            class="flex items-center gap-2 px-4 py-2 text-sm focus-visible:outline-hidden bg-gray-800 text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.plus class="w-4 h-4" />
                            {{ __('New conversation') }}
                        </button>
                        <a
                            href="{{ route('chat') }}"
                            wire:navigate
                            x-on:click="chatDropdownOpen = false"
                            class="flex items-center gap-2 px-4 py-2 text-sm focus-visible:outline-hidden bg-gray-800 text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.arrow-right class="w-4 h-4" />
                            {{ __('View all conversations') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="px-4 py-8 text-center">
                    <flux:icon
                        name="chat-bubble-left-right"
                        class="w-12 h-12 mx-auto text-slate-600 mb-3"
                    />
                    <p class="text-sm text-slate-400 mb-4">
                        {{ __('No conversations yet') }}
                    </p>
                    <flux:button
                        x-on:click="$wire.showNewConversation = true; chatDropdownOpen = false"
                        size="sm"
                        variant="primary"
                    >
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
        :search-results="$this->searchResults"
    />

</div>