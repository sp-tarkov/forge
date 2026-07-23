<x-slot:title>
    {{ __('Forge Conversations') }}
</x-slot>

<x-slot:description>
    {{ __('Connect and chat with other members of The Forge community.') }}
</x-slot>

<x-slot:header>
    <h2 class="text-xl font-semibold leading-tight text-gray-200">
        {{ __('Forge Conversations') }}
    </h2>
</x-slot>

<div class="relative mx-auto max-w-7xl px-4 pt-6 sm:px-6 sm:pt-0 lg:px-8">
    {{-- New Conversation Button (desktop only; mobile uses the inline button in the conversations list) --}}
    <div class="absolute -top-[108px] right-4 hidden sm:right-6 lg:right-8 lg:block">
        <flux:button
            variant="primary"
            icon="plus"
            wire:click="openNewConversationModal"
        >
            {{ __('New Conversation') }}
        </flux:button>
    </div>

    {{-- Chat Interface --}}
    <div
        x-data="{ mobileView: @js($selectedConversation ? 'thread' : 'list') }"
        class="flex h-[calc(100vh-160px)] flex-col gap-4 lg:h-auto lg:max-h-[80vh] lg:min-h-[400px] lg:flex-row"
        wire:key="chat-interface"
    >
        {{-- Left Column: Conversations List --}}
        <div
            :class="mobileView === 'list' ? 'flex' : 'hidden lg:flex'"
            class="h-full min-h-0 w-full flex-col overflow-hidden rounded-lg border border-gray-800 bg-gray-900 shadow-sm lg:h-auto lg:max-h-[600px] lg:min-h-[400px] lg:w-80"
            wire:key="conversations-list"
        >
            {{-- Mobile-only New Conversation header (desktop uses the absolutely-positioned button above the panel) --}}
            <div class="border-b border-gray-800 p-3 lg:hidden">
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="openNewConversationModal"
                    class="w-full"
                >
                    {{ __('New Conversation') }}
                </flux:button>
            </div>

            {{-- Conversations List --}}
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $conversation)
                    @if ($conversation->other_user)
                        <button
                            type="button"
                            wire:key="conversation-{{ $conversation->id }}"
                            wire:click="switchConversation('{{ $conversation->hash_id }}')"
                            x-on:click="mobileView = 'thread'"
                            class="{{ $selectedConversation?->id === $conversation->id
                                ? 'bg-gray-950 border-l-4 border-blue-500 shadow-sm'
                                : 'hover:bg-blue-950 hover:shadow-sm' }} flex w-full items-center gap-3 p-4 text-left transition-all duration-200"
                        >
                            <div class="relative inline-block">
                                <flux:avatar
                                    src="{{ $conversation->other_user->profile_photo_url }}"
                                    size="md"
                                    circle
                                    color="auto"
                                    color:seed="{{ $conversation->other_user->id }}"
                                />
                                @if ($this->isUserOnline($conversation->other_user))
                                    <span
                                        class="absolute bottom-0 right-0 block h-3 w-3 rounded-full bg-green-400 ring-2 ring-gray-900"
                                    ></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <div class="truncate font-medium">
                                            <x-user-name :user="$conversation->other_user" />
                                        </div>
                                        @if ($selectedConversation?->id !== $conversation->id && $conversation->unread_count > 0)
                                            <flux:badge
                                                color="red"
                                                size="sm"
                                                class="flex-shrink-0"
                                            >{{ $conversation->unread_count }}</flux:badge>
                                        @endif
                                    </div>
                                    @if ($conversation->lastMessage)
                                        <div class="flex-shrink-0 text-xs text-gray-400">
                                            {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                        </div>
                                    @endif
                                </div>
                                @if ($conversation->lastMessage)
                                    <div class="truncate text-sm text-gray-400">
                                        {{ Str::limit($conversation->lastMessage->content, 50) }}
                                    </div>
                                @else
                                    <div class="text-sm italic text-gray-500">
                                        No messages yet
                                    </div>
                                @endif
                            </div>
                        </button>
                    @endif
                @empty
                    <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                        <flux:icon
                            name="chat-bubble-left-right"
                            class="mb-4 h-16 w-16 text-gray-600"
                        />
                        <p class="text-gray-400">
                            {{ __('No conversations yet') }}
                        </p>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ __('Click "New Conversation" above to start') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right Column: Selected Conversation --}}
        <div
            :class="mobileView === 'thread' ? 'flex' : 'hidden lg:flex'"
            class="h-full min-h-0 w-full flex-col overflow-hidden rounded-lg border border-gray-800 bg-gray-950 shadow-sm lg:h-auto lg:max-h-[600px] lg:min-h-[400px] lg:flex-1"
            wire:key="conversation-{{ $selectedConversation?->id ?? 'none' }}"
        >
            @if ($selectedConversation)
                {{-- Conversation Header --}}
                <div class="flex items-center justify-between border-b border-gray-800 bg-gray-900 p-4">
                    @if ($selectedConversation->other_user)
                        <div class="flex items-center gap-3">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                square
                                class="-ml-2 lg:hidden"
                                x-on:click="mobileView = 'list'"
                                aria-label="{{ __('Back to conversations') }}"
                            >
                                <flux:icon.arrow-left class="h-5 w-5" />
                            </flux:button>
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
                                <div class="font-medium">
                                    <x-user-name :user="$selectedConversation->other_user" />
                                </div>
                                <div class="text-xs">
                                    @if ($this->isUserOnline($selectedConversation->other_user))
                                        <span class="text-green-400">{{ __('Online') }}</span>
                                    @else
                                        <span
                                            class="text-gray-400">{{ $this->getUserLastSeen($selectedConversation->other_user) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                square
                                class="-ml-2 lg:hidden"
                                x-on:click="mobileView = 'list'"
                                aria-label="{{ __('Back to conversations') }}"
                            >
                                <flux:icon.arrow-left class="h-5 w-5" />
                            </flux:button>
                            <div class="font-medium text-gray-100">{{ __('Loading...') }}</div>
                        </div>
                    @endif

                    {{-- Conversation Actions --}}
                    @if ($selectedConversation->other_user)
                        <flux:dropdown align="end">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                square
                            >
                                <flux:icon.ellipsis-vertical class="h-5 w-5" />
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item
                                    icon="user"
                                    href="{{ route('user.show', ['userId' => $selectedConversation->other_user->id, 'slug' => Str::slug($selectedConversation->other_user->name)]) }}"
                                >{{ __('View Profile') }}</flux:menu.item>
                                <flux:menu.separator />
                                @if ($this->isNotificationEnabled())
                                    <flux:menu.item
                                        icon="bell-slash"
                                        wire:click="toggleNotifications"
                                    >{{ __('Disable Notifications') }}</flux:menu.item>
                                @else
                                    <flux:menu.item
                                        icon="bell"
                                        wire:click="toggleNotifications"
                                    >{{ __('Enable Notifications') }}</flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item
                                    icon="archive-box"
                                    variant="danger"
                                    wire:click="openArchiveModal"
                                >{{ __('Archive Conversation') }}</flux:menu.item>
                                @if ($this->isUserBlocked)
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        icon="shield-check"
                                        wire:click="openBlockModal"
                                    >{{ __('Unblock User') }}</flux:menu.item>
                                @elseif (auth()->user()?->can('block', $selectedConversation->other_user))
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        icon="shield-exclamation"
                                        variant="danger"
                                        wire:click="openBlockModal"
                                    >{{ __('Block User') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>

                {{-- Messages Area --}}
                <div
                    class="flex-1 space-y-4 overflow-y-auto bg-gradient-to-b from-gray-950/50 to-gray-950 p-4"
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
                >

                    {{-- Load More Trigger (Sentinel Element) --}}
                    @if ($hasMoreMessages)
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
                            <div
                                wire:loading
                                wire:target="loadMoreMessages"
                            >
                                <div class="rounded-lg border border-cyan-800 bg-cyan-950 px-4 py-2">
                                    <svg
                                        class="mr-2 inline-block h-4 w-4 animate-spin text-cyan-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    <span
                                        class="text-sm font-medium text-cyan-300">{{ __('Loading earlier messages...') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- No more messages indicator --}}
                    @if (!$hasMoreMessages && count($messages) > 0)
                        <div class="flex justify-center py-4">
                            <div class="rounded-lg border border-gray-800 bg-gray-900 px-4 py-2">
                                <span
                                    class="text-sm font-medium text-gray-400">{{ __('Beginning of conversation') }}</span>
                            </div>
                        </div>
                    @endif

                    @forelse($messages as $index => $message)
                        <div wire:key="message-{{ $message->id }}">

                            {{-- Show separator after the last newly loaded message --}}
                            @if ($pagesLoaded > 1 && $index === $perPage)
                                <div
                                    class="mb-4 flex items-center gap-3 py-2"
                                    wire:key="separator-current"
                                >
                                    <div class="h-px flex-1 bg-gray-700"></div>
                                    <span class="px-2 text-xs text-gray-400">Recently loaded
                                        above</span>
                                    <div class="h-px flex-1 bg-gray-700"></div>
                                </div>
                            @endif

                            @if ($message->is_mine)
                                {{-- Message from current user --}}
                                <div class="message-item flex justify-end">
                                    <div class="flex max-w-[85%] flex-row-reverse items-start gap-2 lg:max-w-[70%]">
                                        <flux:avatar
                                            src="{{ auth()->user()->profile_photo_url }}"
                                            size="sm"
                                            circle
                                            color="auto"
                                            color:seed="{{ auth()->user()->id }}"
                                            class="flex-shrink-0"
                                        />
                                        <div class="flex flex-col items-end">
                                            <div class="rounded-lg bg-blue-500 px-3 py-1.5 text-white shadow-sm">
                                                <div class="user-markdown-message max-w-none text-sm">
                                                    {!! $message->content_html !!}</div>
                                            </div>
                                            <div class="mt-1 px-2 text-xs text-gray-400">
                                                {{ $message->created_at->dynamicFormat() }}
                                                @if ($message->is_read)
                                                    {{-- Double check for read --}}
                                                    <span class="ml-1 inline-flex">
                                                        <flux:icon.check class="h-3 w-3 text-blue-500" />
                                                        <flux:icon.check class="-ml-1 h-3 w-3 text-blue-500" />
                                                    </span>
                                                @else
                                                    {{-- Single check for sent --}}
                                                    <flux:icon.check class="ml-1 inline h-3 w-3" />
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Message from other user --}}
                                <div class="message-item flex justify-start">
                                    <div class="flex max-w-[85%] items-start gap-2 lg:max-w-[70%]">
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
                                            <div class="rounded-lg bg-gray-900 px-3 py-1.5 text-gray-100 shadow-sm">
                                                <div class="user-markdown-message max-w-none text-sm">
                                                    {!! $message->content_html !!}</div>
                                            </div>
                                            <div class="mt-1 px-2 text-xs text-gray-400">
                                                {{ $message->created_at->dynamicFormat() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <flux:icon
                                name="chat-bubble-left-right"
                                class="mb-3 h-12 w-12 text-gray-600"
                            />
                            <p class="text-gray-400">
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
                        dots: '...',
                        interval: null,
                        init() {
                            // Watch for changes in typing users from Livewire
                            this.$watch('$wire.typingUsers', (value) => {
                                const users = Object.values(value || {});
                                if (users.length === 0) {
                                    this.show = false;
                                    this.message = this.cachedMessage || ''; // Keep cached message for fade out
                                    this.stopDotsAnimation();
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
                                    this.startDotsAnimation();
                                }
                            });
                        },
                        startDotsAnimation() {
                            if (this.interval) return; // Already animating
                            const sequence = ['...', '..', '.', '..'];
                            let index = 0;
                            this.interval = setInterval(() => {
                                this.dots = sequence[index];
                                index = (index + 1) % sequence.length;
                            }, 400);
                        },
                        stopDotsAnimation() {
                            if (this.interval) {
                                clearInterval(this.interval);
                                this.interval = null;
                                this.dots = '...';
                            }
                        },
                        destroy() {
                            this.stopDotsAnimation();
                        }
                    }"
                    x-init="init()"
                    x-destroy="destroy()"
                    x-cloak
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="px-4 pb-2 text-sm italic text-gray-400"
                >
                    <div class="flex items-center gap-1">
                        <span x-text="message"></span>
                        <span
                            x-show="message && message.length > 0"
                            class="inline-block w-8 text-left"
                        >
                            <span x-text="dots"></span>
                        </span>
                    </div>
                </div>

                {{-- Message Input Area or Blocked Message --}}
                @if ($this->isConversationBlocked)
                    <div class="border-t border-gray-800 bg-gray-900 p-4 text-center">
                        <div class="text-gray-400">
                            <flux:icon
                                name="shield-exclamation"
                                class="mx-auto mb-2 h-8 w-8"
                            />
                            <p class="text-sm font-medium">{{ __('You can\'t send messages in this conversation') }}
                            </p>
                            <p class="mt-1 text-xs">{{ __('One or both users have blocked this conversation') }}</p>
                        </div>
                    </div>
                @else
                    <div
                        class="border-t border-gray-800 bg-gray-900 p-4"
                        wire:key="message-input-area"
                        x-data="{
                            message: $wire.entangle('messageText'),
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
                                return 'text-gray-400';
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
                        }"
                    >
                        <form
                            wire:submit="sendMessage"
                            class="flex gap-2"
                            x-ref="sendForm"
                        >
                            <flux:textarea
                                wire:model="messageText"
                                x-ref="messageInput"
                                x-on:input="resizeTextarea(); handleTyping()"
                                x-on:keydown="handleKeydown"
                                placeholder="{{ __('Type a message...') }}"
                                rows="1"
                                class="min-h-[46px] flex-1 resize-none"
                                maxlength="500"
                                autofocus
                            />
                            <flux:button
                                type="submit"
                                variant="primary"
                                square
                                class="shadow-sm transition-shadow duration-200 hover:shadow-md"
                                x-bind:style="'height: ' + buttonHeight"
                            >
                                <flux:icon.paper-airplane class="size-5" />
                            </flux:button>
                        </form>
                        <div
                            x-show="showCounter"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform translate-y-1"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-1"
                            class="mt-2 text-right text-xs"
                            :class="counterColor"
                        >
                            <span x-text="charCount"></span>/500 characters
                        </div>
                    </div>
                @endif
            @else
                {{-- No conversation selected --}}
                <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <flux:icon
                        name="chat-bubble-left-right"
                        class="mb-4 h-20 w-20 text-gray-600"
                    />
                    <h3 class="mb-2 text-lg font-medium text-gray-100">
                        {{ __('Select a conversation') }}
                    </h3>
                    <p class="text-gray-400">
                        {{ __('Choose a conversation from the list or click "New Conversation" above to start a new one') }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Archive Conversation Modal --}}
    <flux:modal
        wire:model.live="showArchiveModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="archive-box"
                        class="h-8 w-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Archive Conversation') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Remove this conversation from your list') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    {{ __('Are you sure you want to archive this conversation?') }}
                </flux:text>
                <flux:text class="text-sm text-gray-300">
                    {{ __('The conversation will be hidden from your conversation list. It will automatically reappear when:') }}
                </flux:text>
                <ul class="ml-4 list-inside list-disc space-y-1 text-sm text-gray-300">
                    <li>{{ __('The other user sends you a new message') }}</li>
                    <li>{{ __('You start a new conversation with this user') }}</li>
                </ul>
                <flux:text class="text-sm italic text-gray-400">
                    {{ __('Note: Messages are not deleted. The other user can still see this conversation.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    wire:click="closeArchiveModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="archiveConversation"
                    variant="primary"
                    size="sm"
                    icon="archive-box"
                    class="bg-amber-600 text-white hover:bg-amber-700"
                >
                    {{ __('Archive') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Block User Modal --}}
    @if (
        $selectedConversation?->other_user &&
            ($this->isUserBlocked || auth()->user()?->can('block', $selectedConversation->other_user)))
        <flux:modal
            wire:model.live="showBlockModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        @if ($this->isUserBlocked)
                            <flux:icon
                                name="shield-check"
                                class="h-8 w-8 text-green-600"
                            />
                            <div>
                                <flux:heading
                                    size="xl"
                                    class="text-gray-100"
                                >
                                    {{ __('Unblock User') }}
                                </flux:heading>
                                <flux:text class="mt-1 text-sm text-gray-400">
                                    {{ __('Allow messages and interactions again') }}
                                </flux:text>
                            </div>
                        @else
                            <flux:icon
                                name="shield-exclamation"
                                class="h-8 w-8 text-red-600"
                            />
                            <div>
                                <flux:heading
                                    size="xl"
                                    class="text-gray-100"
                                >
                                    {{ __('Block User') }}
                                </flux:heading>
                                <flux:text class="mt-1 text-sm text-gray-400">
                                    {{ __('Prevent messages and interactions') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    @if ($this->isUserBlocked())
                        <flux:text class="text-sm text-gray-300">
                            {{ __('Are you sure you want to unblock this user? You will be able to send and receive messages again.') }}
                        </flux:text>
                    @else
                        <flux:text class="text-sm text-gray-300">
                            {{ __('Are you sure you want to block this user?') }}
                        </flux:text>

                        <div class="space-y-2">
                            <flux:text class="text-sm font-medium text-gray-100">
                                {{ __('What happens when you block someone:') }}
                            </flux:text>
                            <ul class="ml-2 list-inside list-disc space-y-1 text-sm text-gray-300">
                                <li>{{ __('You won\'t be able to send messages to each other') }}</li>
                                <li>{{ __('They won\'t be able to see your profile') }}</li>
                                <li>{{ __('They can\'t comment on your mods') }}</li>
                                <li>{{ __('You can unblock them anytime') }}</li>
                            </ul>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Reason (optional)') }}</flux:label>
                            <flux:textarea
                                wire:model="blockReason"
                                rows="2"
                                placeholder="{{ __('Why are you blocking this user?') }}"
                            />
                        </flux:field>
                    @endif
                </div>

                {{-- Footer Section --}}
                <div class="mt-6 border-t border-gray-700 pt-6">
                    <div class="flex justify-end gap-3">
                        <flux:button
                            variant="ghost"
                            wire:click="closeBlockModal"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        @if ($this->isUserBlocked)
                            <flux:button
                                variant="primary"
                                wire:click="confirmBlock"
                            >
                                {{ __('Unblock User') }}
                            </flux:button>
                        @else
                            <flux:button
                                variant="danger"
                                wire:click="confirmBlock"
                            >
                                {{ __('Block User') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- New Conversation Modal --}}
    <x-new-conversation-modal
        :show-modal="'showNewConversation'"
        :search-user="$searchUser"
        :search-results="$searchResults"
    />

</div>
