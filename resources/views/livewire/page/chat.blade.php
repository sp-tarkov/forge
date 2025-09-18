<x-slot:title>
    {{ __('Forge Conversations') }}
</x-slot>

<x-slot:description>
    {{ __('Connect and chat with other members of The Forge community.') }}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Forge Conversations') }}
    </h2>
</x-slot>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
    {{-- New Conversation Button --}}
    <div class="absolute -top-[108px] right-4 sm:right-6 lg:right-8">
        <flux:button
            variant="primary"
            icon="plus"
            wire:click="openNewConversationModal">
            {{ __('New Conversation') }}
        </flux:button>
    </div>

    {{-- Chat Interface --}}
    <div class="flex gap-4 min-h-[400px] max-h-[80vh]" wire:key="chat-interface">
            {{-- Left Column: Conversations List --}}
            <div class="w-80 bg-gray-100 dark:bg-gray-900 rounded-lg border border-gray-300 dark:border-gray-800 shadow-sm overflow-hidden min-h-[400px] max-h-[600px] flex flex-col" wire:key="conversations-list">
                {{-- Conversations List --}}
                <div class="flex-1 overflow-y-auto">
                    @forelse($conversations as $conversation)
                        @if($conversation->other_user)
                            <button
                                type="button"
                                wire:key="conversation-{{ $conversation->id }}"
                                wire:click="switchConversation('{{ $conversation->hash_id }}')"
                                class="w-full flex items-center gap-3 p-4 transition-all duration-200 text-left
                                    {{ $selectedConversation?->id === $conversation->id
                                        ? 'bg-gray-50 dark:bg-gray-950 border-l-4 border-blue-500 shadow-sm'
                                        : 'hover:bg-blue-100 dark:hover:bg-blue-950 hover:shadow-sm' }}"
                            >
                                    <div class="relative inline-block">
                                        <flux:avatar
                                            src="{{ $conversation->other_user->profile_photo_url }}"
                                            size="md"
                                            circle
                                            color="auto"
                                            color:seed="{{ $conversation->other_user->id }}"
                                        />
                                        @if($this->isUserOnline($conversation->other_user->id))
                                            <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"></span>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                                    {{ $conversation->other_user->name }}
                                                </div>
                                                @if($selectedConversation?->id !== $conversation->id && $conversation->unread_count > 0)
                                                    <flux:badge color="red" size="sm" class="flex-shrink-0">{{ $conversation->unread_count }}</flux:badge>
                                                @endif
                                            </div>
                                            @if($conversation->lastMessage)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                                    {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                </div>
                                            @endif
                                        </div>
                                        @if($conversation->lastMessage)
                                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                                {{ Str::limit($conversation->lastMessage->content, 50) }}
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-500 dark:text-gray-500 italic">
                                                No messages yet
                                            </div>
                                        @endif
                                    </div>
                            </button>
                        @endif
                    @empty
                        <div class="flex flex-col items-center justify-center h-full p-8 text-center">
                            <flux:icon name="chat-bubble-left-right" class="w-16 h-16 text-gray-400 dark:text-gray-600 mb-4" />
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ __('No conversations yet') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                                {{ __('Click "New Conversation" above to start') }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Right Column: Selected Conversation --}}
            <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-300 dark:border-gray-800 shadow-sm overflow-hidden min-h-[400px] max-h-[600px]" wire:key="conversation-container">
                @if($selectedConversation)
                    {{-- Conversation Header --}}
                    <div class="p-4 border-b border-gray-300 dark:border-gray-800 flex items-center justify-between bg-gray-100 dark:bg-gray-900">
                        @if($selectedConversation->other_user)
                        <div class="flex items-center gap-3">
                            <div class="relative inline-block">
                                <flux:avatar
                                    src="{{ $selectedConversation->other_user->profile_photo_url }}"
                                    size="sm"
                                    circle
                                    color="auto"
                                    color:seed="{{ $selectedConversation->other_user->id }}"
                                />
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $selectedConversation->other_user->name }}</div>
                                <div class="text-xs">
                                    @if($this->isUserOnline($selectedConversation->other_user->id))
                                        <span class="text-green-500 dark:text-green-400">{{ __('Online') }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">{{ $this->getUserLastSeen($selectedConversation->other_user->id) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="flex items-center gap-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Loading...') }}</div>
                        </div>
                        @endif

                        {{-- Conversation Actions --}}
                        @if($selectedConversation->other_user)
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" square>
                                <flux:icon.ellipsis-vertical class="h-5 w-5" />
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item icon="user" href="{{ route('user.show', ['userId' => $selectedConversation->other_user->id, 'slug' => Str::slug($selectedConversation->other_user->name)]) }}">{{ __('View Profile') }}</flux:menu.item>
                                <flux:menu.separator />
                                @if($this->isNotificationEnabled())
                                    <flux:menu.item icon="bell-slash" wire:click="toggleNotifications">{{ __('Disable Notifications') }}</flux:menu.item>
                                @else
                                    <flux:menu.item icon="bell" wire:click="toggleNotifications">{{ __('Enable Notifications') }}</flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item icon="archive-box" variant="danger" wire:click="openArchiveModal">{{ __('Archive Conversation') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                        @endif
                    </div>

                    {{-- Messages Area --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gradient-to-b from-gray-100/50 to-gray-50 dark:from-gray-950/50 dark:to-gray-950"
                         wire:key="messages-area"
                         id="messages-container"
                         x-data="{
                             scrollToBottom() {
                                 this.$el.scrollTop = this.$el.scrollHeight;
                             },
                             init() {
                                 this.$nextTick(() => this.scrollToBottom());
                                 Livewire.on('messages-updated', () => {
                                     this.$nextTick(() => this.scrollToBottom());
                                 });
                                 Livewire.on('conversation-switched', () => {
                                     this.$nextTick(() => this.scrollToBottom());
                                 });
                             }
                         }"
                         x-init="init">

                        {{-- Load More Trigger (Sentinel Element) --}}
                        @if($hasMoreMessages)
                            <div
                                x-data="{
                                    hasScrolledUp: false,
                                    lastScrollTop: 0,
                                    init() {
                                        const container = document.getElementById('messages-container');

                                        // Track if user has intentionally scrolled up
                                        container.addEventListener('scroll', () => {
                                            const currentScroll = container.scrollTop;
                                            if (currentScroll < this.lastScrollTop && currentScroll < 100) {
                                                this.hasScrolledUp = true;
                                            }
                                            this.lastScrollTop = currentScroll;
                                        });
                                    },
                                    loadMore() {
                                        if (!this.hasScrolledUp) return;
                                        this.hasScrolledUp = false;

                                        // Find the oldest visible message
                                        const container = document.getElementById('messages-container');
                                        const messages = container.querySelectorAll('.message-item');
                                        const firstMessage = messages[0];
                                        if (!firstMessage) return;

                                        const firstMessageOffset = firstMessage.offsetTop;

                                        $wire.loadMoreMessages().then(() => {
                                            $nextTick(() => {
                                                // Find the same message in the refreshed listing and scroll to it
                                                const newMessages = container.querySelectorAll('.message-item');
                                                const newMessagesCount = newMessages.length - messages.length;
                                                if (newMessagesCount > 0 && newMessages[newMessagesCount]) {
                                                    const oldFirstMessage = newMessages[newMessagesCount];
                                                    container.scrollTop = oldFirstMessage.offsetTop - firstMessageOffset;
                                                }

                                                this.hasScrolledUp = false;
                                            });
                                        });
                                    }
                                }"
                                x-intersect:enter="loadMore()"
                                wire:key="load-trigger"
                                class="flex justify-center py-2"
                            >
                                {{-- Loading indicator --}}
                                <div wire:loading wire:target="loadMoreMessages">
                                    <div class="px-4 py-2 bg-cyan-50 dark:bg-cyan-950 border border-cyan-200 dark:border-cyan-800 rounded-lg">
                                        <svg class="inline-block animate-spin h-4 w-4 text-cyan-600 dark:text-cyan-400 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-cyan-700 dark:text-cyan-300">{{ __('Loading earlier messages...') }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- No more messages indicator --}}
                        @if(!$hasMoreMessages && count($messages) > 0)
                            <div class="flex justify-center py-4">
                                <div class="px-4 py-2 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Beginning of conversation') }}</span>
                                </div>
                            </div>
                        @endif

                        @forelse($messages as $index => $message)
                            <div wire:key="message-{{ $message->id }}">

                            {{-- Show separator after the last newly loaded message --}}
                            @if($pagesLoaded > 1 && $index === $perPage)
                                <div class="flex items-center gap-3 py-2 mb-4" wire:key="separator-current">
                                    <div class="flex-1 h-px bg-gray-300 dark:bg-gray-700"></div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 px-2">Recently loaded above</span>
                                    <div class="flex-1 h-px bg-gray-300 dark:bg-gray-700"></div>
                                </div>
                            @endif

                            @if($message->is_mine)
                                {{-- Message from current user --}}
                                <div class="flex justify-end message-item">
                                    <div class="flex gap-2 max-w-[70%] flex-row-reverse items-start">
                                        <flux:avatar
                                            src="{{ auth()->user()->profile_photo_url }}"
                                            size="sm"
                                            circle
                                            color="auto"
                                            color:seed="{{ auth()->user()->id }}"
                                            class="flex-shrink-0"
                                        />
                                        <div class="flex flex-col items-end">
                                            <div class="px-3 py-1.5 rounded-lg bg-blue-500 text-white shadow-sm">
                                                <div class="text-sm user-markdown-message max-w-none">{!! $message->content_html !!}</div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">
                                                {{ $message->created_at->dynamicFormat() }}
                                                @if($message->is_read)
                                                    {{-- Double check for read --}}
                                                    <span class="inline-flex ml-1">
                                                        <flux:icon.check class="h-3 w-3 text-blue-500" />
                                                        <flux:icon.check class="h-3 w-3 -ml-1 text-blue-500" />
                                                    </span>
                                                @else
                                                    {{-- Single check for sent --}}
                                                    <flux:icon.check class="inline h-3 w-3 ml-1" />
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Message from other user --}}
                                <div class="flex justify-start message-item">
                                    <div class="flex gap-2 max-w-[70%] items-start">
                                        <div class="relative inline-block flex-shrink-0">
                                            <flux:avatar
                                                src="{{ $message->user->profile_photo_url }}"
                                                size="sm"
                                                circle
                                                color="auto"
                                                color:seed="{{ $message->user->id }}"
                                            />
                                        </div>
                                        <div class="flex flex-col items-start">
                                            <div class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm">
                                                <div class="text-sm user-markdown-message max-w-none">{!! $message->content_html !!}</div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">
                                                {{ $message->created_at->dynamicFormat() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-full text-center">
                                <flux:icon name="chat-bubble-left-right" class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-3" />
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{ __('No messages yet. Start the conversation!') }}
                                </p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Typing Indicator --}}
                    <div
                        x-data="{
                            show: false,
                            message: '',
                            cachedMessage: '',
                            init() {
                                // Watch for changes in typing users from Livewire
                                this.$watch('$wire.typingUsers', (value) => {
                                    const users = Object.values(value || {});
                                    if (users.length === 0) {
                                        this.show = false;
                                        this.message = this.cachedMessage || ''; // Keep cached message for fade out
                                    } else {
                                        // Build the message based on number of users
                                        let newMessage = '';
                                        if (users.length === 1) {
                                            newMessage = users[0].name + ' is typing';
                                        } else if (users.length === 2) {
                                            newMessage = users[0].name + ' and ' + users[1].name + ' are typing';
                                        } else {
                                            newMessage = users.length + ' people are typing';
                                        }

                                        this.message = newMessage;
                                        this.cachedMessage = newMessage;
                                        this.show = true;
                                    }
                                });
                            }
                        }"
                        x-cloak
                        x-show="show"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="px-4 pb-2 text-sm text-gray-500 dark:text-gray-400 italic"
                    >
                        <div class="flex items-center gap-1">
                            <span x-text="message"></span>
                            <span x-show="message.length > 0" class="inline-block w-8 text-left">
                                <span x-data="{
                                    dots: '...',
                                    interval: null,
                                    animateDots() {
                                        const sequence = ['...', '..', '.', '..'];
                                        let index = 0;
                                        this.interval = setInterval(() => {
                                            this.dots = sequence[index];
                                            index = (index + 1) % sequence.length;
                                        }, 400);
                                    },
                                    destroy() {
                                        if (this.interval) {
                                            clearInterval(this.interval);
                                        }
                                    }
                                }"
                                x-init="animateDots()"
                                x-destroy="destroy()"
                                x-text="dots"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Message Input Area --}}
                    <div class="p-4 border-t border-gray-300 dark:border-gray-800 bg-gray-100 dark:bg-gray-900"
                         wire:key="message-input-area"
                         x-data="{
                             message: @entangle('messageText'),
                             maxLength: 500,
                             buttonHeight: '46px',
                             typingTimer: null,
                             typingDebounce: 1500,
                             get charCount() { return this.message.length; },
                             get remaining() { return this.maxLength - this.charCount; },
                             get showCounter() { return this.charCount >= 350; },
                             get counterColor() {
                                 if (this.remaining <= 10) return 'text-red-500';
                                 if (this.remaining <= 75) return 'text-amber-500';
                                 return 'text-gray-500 dark:text-gray-400';
                             },
                             handleTyping() {
                                 if (this.typingTimer) {
                                     clearTimeout(this.typingTimer);
                                 }

                                 $wire.handleTyping();

                                 this.typingTimer = setTimeout(() => {
                                     $wire.stopTyping();
                                     this.typingTimer = null;
                                 }, this.typingDebounce);
                             },
                             resizeTextarea() {
                                 const textarea = this.$refs.messageInput;
                                 if (textarea) {
                                     textarea.style.height = 'auto';
                                     const newHeight = Math.min(Math.max(textarea.scrollHeight, 46), 120);
                                     textarea.style.height = newHeight + 'px';
                                     this.buttonHeight = newHeight + 'px';
                                 }
                             },
                             resetHeight() {
                                 const textarea = this.$refs.messageInput;
                                 if (textarea) {
                                     textarea.style.height = '46px';
                                     this.buttonHeight = '46px';
                                 }
                             },
                             handleKeydown(event) {
                                 if (event.key === 'Enter' && !event.shiftKey) {
                                     event.preventDefault();
                                     if (this.message.trim() && this.$refs.sendForm) {
                                         // Clear typing timer when sending
                                         if (this.typingTimer) {
                                             clearTimeout(this.typingTimer);
                                             this.typingTimer = null;
                                         }
                                         this.$refs.sendForm.requestSubmit();
                                     }
                                 }
                             },
                             init() {
                                 $watch('message', (value) => {
                                     if (!value) {
                                         this.resetHeight();
                                     }
                                 });
                                 this.$nextTick(() => {
                                     if (this.$refs.messageInput) {
                                         this.$refs.messageInput.focus();
                                     }
                                 });
                                 Livewire.on('messages-updated', () => {
                                     this.$nextTick(() => {
                                         if (this.$refs.messageInput) {
                                             this.$refs.messageInput.focus();
                                         }
                                     });
                                 });
                             }
                         }">
                        <form wire:submit="sendMessage" class="flex gap-2" x-ref="sendForm">
                            <flux:textarea
                                wire:model="messageText"
                                x-ref="messageInput"
                                x-on:input="resizeTextarea(); handleTyping()"
                                x-on:keydown="handleKeydown"
                                placeholder="{{ __('Type a message...') }}"
                                rows="1"
                                class="flex-1 resize-none min-h-[46px]"
                                maxlength="500"
                                autofocus
                            />
                            <flux:button
                                type="submit"
                                variant="primary"
                                square
                                class="shadow-sm hover:shadow-md transition-shadow duration-200"
                                x-bind:style="'height: ' + buttonHeight"
                            >
                                <flux:icon.paper-airplane class="size-5" />
                            </flux:button>
                        </form>
                        <div x-show="showCounter"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform translate-y-1"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform translate-y-1"
                             class="text-xs text-right mt-2"
                             :class="counterColor">
                            <span x-text="charCount"></span>/500 characters
                        </div>
                    </div>
                @else
                    {{-- No conversation selected --}}
                    <div class="flex flex-col items-center justify-center h-full text-center p-8">
                        <flux:icon name="chat-bubble-left-right" class="w-20 h-20 text-gray-400 dark:text-gray-600 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Select a conversation') }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            {{ __('Choose a conversation from the list or click "New Conversation" above to start a new one') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

    {{-- Archive Conversation Modal --}}
    <flux:modal wire:model.live="showArchiveModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="archive-box" class="w-8 h-8 text-amber-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Archive Conversation') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Remove this conversation from your list') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to archive this conversation?') }}
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('The conversation will be hidden from your conversation list. It will automatically reappear when:') }}
                </flux:text>
                <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 text-sm space-y-1 ml-4">
                    <li>{{ __('The other user sends you a new message') }}</li>
                    <li>{{ __('You start a new conversation with this user') }}</li>
                </ul>
                <flux:text class="text-gray-600 dark:text-gray-400 text-sm italic">
                    {{ __('Note: Messages are not deleted. The other user can still see this conversation.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button wire:click="closeArchiveModal" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="archiveConversation" variant="primary" size="sm" icon="archive-box" class="bg-amber-600 hover:bg-amber-700 text-white">
                    {{ __('Archive') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- New Conversation Modal --}}
    <x-new-conversation-modal
        :show-modal="'showNewConversation'"
        :search-user="$searchUser"
        :search-results="$searchResults" />

    {{-- Presence channel (typing) --}}
    @script
        <script>
            let currentConversationChannel = null;
            $wire.on('join-conversation-presence', ({ conversationHash }) => {
                if (!window.Echo || !conversationHash) return;
                if (currentConversationChannel) {
                    window.Echo.leave(currentConversationChannel);
                }
                currentConversationChannel = `presence.conversation.${conversationHash}`;
                window.Echo.join(currentConversationChannel)
                    .leaving((user) => $wire.handleUserLeavingConversation(user))
                    .listen('UserStartedTyping', (e) => $wire.handleUserStartedTyping(e))
                    .listen('UserStoppedTyping', (e) => $wire.handleUserStoppedTyping(e));
            });
        </script>
    @endscript
</div>
