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
                    class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
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
            class="absolute right-0 top-11 z-[100] flex w-full min-w-[20rem] flex-col overflow-hidden rounded-xl border border-gray-700 bg-gray-800"
            role="menu"
        >
            @if ($this->conversations->count() > 0)
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-700 px-4 py-3">
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
                                    class="focus-visible:outline-hidden block w-full px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
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
                                                    class="absolute bottom-0 right-0 block h-2 w-2 rounded-full bg-green-400 ring-2 ring-gray-900"
                                                ></span>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="truncate text-sm font-medium">
                                                        <x-user-name :user="$conversation->other_user" />
                                                    </span>
                                                    @if ($conversation->unread_count > 0)
                                                        <flux:badge
                                                            color="red"
                                                            size="sm"
                                                            class="px-1.5 py-0 text-[10px]"
                                                        >{{ $conversation->unread_count }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if ($conversation->lastMessage)
                                                    <div class="flex-shrink-0 text-xs text-slate-400">
                                                        {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($conversation->lastMessage)
                                                <div class="mt-0.5 truncate text-xs text-slate-400">
                                                    {{ Str::limit($conversation->lastMessage->content ?? '', 40) }}
                                                </div>
                                            @else
                                                <div class="mt-0.5 text-xs text-slate-500">
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
                            class="focus-visible:outline-hidden flex items-center gap-2 bg-gray-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.plus class="h-4 w-4" />
                            {{ __('New conversation') }}
                        </button>
                        <a
                            href="{{ route('chat') }}"
                            wire:navigate
                            x-on:click="chatDropdownOpen = false"
                            class="focus-visible:outline-hidden flex items-center gap-2 bg-gray-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-100/5 hover:text-white focus-visible:bg-slate-100/10 focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.arrow-right class="h-4 w-4" />
                            {{ __('View all conversations') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="px-4 py-8 text-center">
                    <flux:icon
                        name="chat-bubble-left-right"
                        class="mx-auto mb-3 h-12 w-12 text-slate-600"
                    />
                    <p class="mb-4 text-sm text-slate-400">
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
