<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    /**
     * Controls visibility of the new conversation modal.
     */
    public bool $showNewConversation = false;

    /**
     * Search query for finding users in the new conversation modal.
     */
    public string $searchUser = '';

    /**
     * Array of currently online user IDs.
     *
     * @var array<int, bool>
     */
    public array $onlineUsers = [];

    /**
     * Current user ID for event listeners.
     */
    public ?int $userId = null;

    /**
     * Array of conversation hash IDs the user is part of.
     *
     * @var array<int, string>
     */
    public array $conversationHashes = [];

    /**
     * Initialize the component
     */
    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $user->updateLastSeen();

        $this->dispatch('join-presence-channel');

        // Set current user ID for private channel listeners
        $this->dispatch('set-user-id', userId: $user->id);

        // Load all conversation hashes for the user
        $this->loadUserConversationHashes();
    }

    /**
     * Mark a conversation as read for the authenticated user.
     */
    public function markAsRead(int $conversationId): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $conversation = Conversation::query()->find($conversationId);

        if ($conversation && $conversation->hasUser($user)) {
            $conversation->markReadBy($user);
        }
    }

    /**
     * Open the new conversation modal.
     */
    public function openNewConversationModal(): void
    {
        $this->showNewConversation = true;
        $this->searchUser = '';
    }

    /**
     * Close the new conversation modal and clear search.
     */
    public function closeNewConversationModal(): void
    {
        $this->showNewConversation = false;
        $this->searchUser = '';
    }

    /**
     * Start a new conversation with the specified user.
     */
    public function startConversation(int $userId): void
    {
        $user = Auth::user();
        $otherUser = User::query()->find($userId);

        if (!$otherUser || $otherUser->id === $user->id) {
            return;
        }

        // Check if either user has blocked the other
        if ($user->hasBlocked($otherUser) || $user->isBlockedBy($otherUser)) {
            flash()->error('You cannot start a conversation with this user.');
            $this->closeNewConversationModal();

            return;
        }

        $existingConversation = Conversation::query()
            ->where(function (Builder $query) use ($user, $otherUser): void {
                $query->where('user1_id', $user->id)->where('user2_id', $otherUser->id);
            })
            ->orWhere(function (Builder $query) use ($user, $otherUser): void {
                $query->where('user1_id', $otherUser->id)->where('user2_id', $user->id);
            })
            ->first();

        $conversation = Conversation::findOrCreateBetween($user, $otherUser, creator: $user);

        // If the conversation is archived for the current user, unarchive it
        if ($conversation->isArchivedBy($user)) {
            $conversation->unarchiveFor($user);
        }

        // If this is a new conversation, update our conversation hashes
        if (!$existingConversation) {
            $this->loadUserConversationHashes();
        }

        $this->closeNewConversationModal();

        $this->redirect($conversation->url, navigate: true);
    }

    /**
     * Navigate to a conversation.
     */
    public function navigateToConversation(string $hashId): void
    {
        if (request()->routeIs('chat')) {
            if (request()->route('conversationHash') !== $hashId) {
                $this->dispatch('switch-conversation', hashId: $hashId);
            }

            $this->dispatch('close-chat-dropdown'); // Close the dropdown

            return;
        }

        $this->redirect(route('chat', ['conversationHash' => $hashId]), navigate: true);
    }

    /**
     * Refresh the component when a conversation is archived.
     */
    public function refreshOnArchive(): void
    {
        // Reload conversation hashes since one was archived
        $this->loadUserConversationHashes();
        // Triggers a render.
    }

    /**
     * Refresh the component when a conversation is updated.
     */
    public function refreshOnUpdate(): void
    {
        // Triggers a render.
    }

    /**
     * Handle user coming online.
     *
     * @param  array<int, array{id: int, name: string, profile_photo_url: string}>  $event
     */
    public function handleUsersHere(array $event): void
    {
        $currentUserId = Auth::id();

        foreach ($event as $user) {
            $this->onlineUsers[$user['id']] = true;

            // Update our own last seen time when we join
            if ($user['id'] === $currentUserId) {
                $currentUser = User::query()->find($currentUserId);
                if ($currentUser) {
                    $currentUser->updateLastSeen();
                }
            }
        }

        // Forward online users state to Chat component
        $this->dispatch('navigation-users-online', onlineUsers: $this->onlineUsers)->to('pages::chat');
    }

    /**
     * Handle user joining (coming online).
     *
     * @param  array{id: int, name: string, profile_photo_url: string}  $event
     */
    public function handleUserJoining(array $event): void
    {
        $this->onlineUsers[$event['id']] = true;

        // Update last seen when user comes online (skip if current user)
        if ($event['id'] !== Auth::id()) {
            User::query()
                ->where('id', $event['id'])
                ->update(['last_seen_at' => now()]);
        }

        // Forward updated online users state to Chat component
        $this->dispatch('navigation-user-joined', userId: $event['id'], onlineUsers: $this->onlineUsers)->to('pages::chat');
    }

    /**
     * Handle user leaving (going offline) with debounce.
     *
     * @param  array{id: int, name: string, profile_photo_url: string}  $event
     */
    public function handleUserLeaving(array $event): void
    {
        // Implement a 5-second debounce before marking user as offline
        $userId = $event['id'];

        // Dispatch JavaScript event to handle the debounced check
        $this->dispatch('debounce-user-offline', userId: $userId);
    }

    /**
     * Check if user is still offline after debounce period.
     */
    public function checkUserOffline(int $userId): void
    {
        // Remove user from online list
        unset($this->onlineUsers[$userId]);

        // Update last seen in database (skip if current user)
        if ($userId !== Auth::id()) {
            User::query()
                ->where('id', $userId)
                ->update(['last_seen_at' => now()]);
        }

        // Forward updated online users state to Chat component
        $this->dispatch('navigation-user-left', userId: $userId, onlineUsers: $this->onlineUsers)->to('pages::chat');
    }

    /**
     * Check if a user is online.
     */
    public function isUserOnline(int $userId): bool
    {
        return isset($this->onlineUsers[$userId]) && $this->onlineUsers[$userId];
    }

    /**
     * Get listeners for this component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = [
            'conversation-archived' => 'refreshOnArchive',
            'conversation-updated' => 'refreshOnUpdate',
            'echo-presence:presence.online,here' => 'handleUsersHere',
            'echo-presence:presence.online,joining' => 'handleUserJoining',
            'echo-presence:presence.online,leaving' => 'handleUserLeaving',
            'check-user-offline' => 'checkUserOffline',
            'conversation-created' => 'handleConversationCreated',
        ];

        // Add dynamic listeners if user is authenticated
        if ($this->userId) {
            $listeners[sprintf('echo-private:user.%s,MessageSent', $this->userId)] = 'handleNewMessage';
            $listeners[sprintf('echo-private:user.%s,ConversationUpdated', $this->userId)] = 'handleConversationUpdated';
        }

        // Add typing listeners for all user's conversations
        foreach ($this->conversationHashes as $hash) {
            $listeners[sprintf('echo-presence:conversation.%s,UserStartedTyping', $hash)] = 'handleTypingStarted';
            $listeners[sprintf('echo-presence:conversation.%s,UserStoppedTyping', $hash)] = 'handleTypingStopped';
        }

        return $listeners;
    }

    /**
     * Handle new message broadcast event.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleNewMessage(array $event): void
    {
        // Forward the message event to the Chat component if it exists
        $this->dispatch('navigation-message-received', $event)->to('pages::chat');

        // If user is on the chat page and viewing this conversation, mark it as read
        if (request()->routeIs('chat') && isset($event['message']['conversation_hash_id'])) {
            $currentConversationHash = request()->route('conversationHash');
            if ($currentConversationHash === $event['message']['conversation_hash_id']) {
                $user = Auth::user();
                $conversation = Conversation::query()->where('hash_id', $event['message']['conversation_hash_id'])->first();

                if ($user && $conversation && $conversation->hasUser($user)) {
                    $conversation->markReadBy($user);
                }
            }
        }

        // Refresh the conversation list when a new message arrives
        $this->dispatch('$refresh');
    }

    /**
     * Handle conversation updated broadcast event.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleConversationUpdated(array $event): void
    {
        // Forward the conversation updated event to the Chat component
        $this->dispatch('navigation-conversation-updated', $event)->to('pages::chat');

        // Refresh the conversation list when a conversation is updated
        $this->dispatch('$refresh');
    }

    /**
     * Handle typing started event from any conversation.
     *
     * @param  array{user_id: int, user_name: string, conversation_hash: string}  $event
     */
    public function handleTypingStarted(array $event): void
    {
        // Don't process for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        // Forward typing event to Chat component
        $this->dispatch('navigation-typing-started', $event)->to('pages::chat');
    }

    /**
     * Handle typing stopped event from any conversation.
     *
     * @param  array{user_id: int, user_name: string, conversation_hash: string}  $event
     */
    public function handleTypingStopped(array $event): void
    {
        // Don't process for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        // Forward typing event to Chat component
        $this->dispatch('navigation-typing-stopped', $event)->to('pages::chat');
    }

    /**
     * Handle when a new conversation is created.
     */
    public function handleConversationCreated(): void
    {
        // Reload conversation hashes to include the new conversation
        $this->loadUserConversationHashes();
    }

    /**
     * Load all conversation hash IDs for the authenticated user.
     */
    private function loadUserConversationHashes(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Load ALL conversations the user is part of (including archived ones)
        // We need this for typing indicators to work properly
        $this->conversationHashes = Conversation::query()->forUser($user)->pluck('hash_id')->toArray();
    }

    /**
     * Fetch recent conversations for the authenticated user.
     *
     * @return Collection<int, Conversation>
     */
    private function fetchConversations(): Collection
    {
        $user = Auth::user();
        if (!$user) {
            return new Collection();
        }

        return Conversation::query()
            ->visibleTo($user)
            ->notArchivedBy($user)
            ->withUserContext($user)
            ->withUnreadCount($user)
            ->with(['user1', 'user2', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Get the count of unread conversations for the authenticated user.
     */
    private function fetchUnreadCount(): int
    {
        $user = Auth::user();
        if (!$user) {
            return 0;
        }

        return Conversation::query()->visibleTo($user)->notArchivedBy($user)->withUnreadMessages($user)->count();
    }

    /**
     * Get search results for finding users to start conversations with.
     *
     * @return Collection<int, User>
     */
    private function fetchSearchResults(): Collection
    {
        if (empty($this->searchUser)) {
            return new Collection();
        }

        return User::query()->conversationSearch(Auth::user(), $this->searchUser)->get();
    }
};
?>

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
            @if ($this->fetchUnreadCount() > 0)
                <span
                    class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
                >
                    {{ $this->fetchUnreadCount() > 9 ? '9+' : $this->fetchUnreadCount() }}
                </span>
            @endif
            <span class="sr-only">{{ __('Chat') }}</span>
        </button>

        @php
            $conversations = $this->fetchConversations();
            $searchResults = $this->fetchSearchResults();
        @endphp

        <div
            x-cloak
            x-show="chatDropdownOpen"
            x-transition
            x-on:click.outside="chatDropdownOpen = false"
            class="absolute top-11 right-0 z-[100] flex w-full min-w-[20rem] flex-col overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
            role="menu"
        >
            @if ($conversations->count() > 0)
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-300 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('Recent Conversations') }}
                    </h3>
                </div>

                <div class="flex flex-col divide-y divide-slate-300 dark:divide-gray-700">
                    <div class="flex flex-col py-2">
                        @foreach ($conversations as $conversation)
                            @if ($conversation->other_user)
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
                                            @if (isset($onlineUsers[$conversation->other_user->id]))
                                                <span
                                                    class="absolute bottom-0 right-0 block h-2 w-2 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"
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
                                                        class="text-xs text-slate-500 dark:text-slate-400 flex-shrink-0">
                                                        {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($conversation->lastMessage)
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
                            class="flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-gray-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                            role="menuitem"
                        >
                            <flux:icon.plus class="w-4 h-4" />
                            {{ __('New conversation') }}
                        </button>
                        <a
                            href="{{ route('chat') }}"
                            wire:navigate
                            x-on:click="chatDropdownOpen = false"
                            class="flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-gray-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
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
                        class="w-12 h-12 mx-auto text-slate-400 dark:text-slate-600 mb-3"
                    />
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
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
        :search-results="$searchResults"
    />

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
            $wire.on('debounce-user-offline', ({
                userId
            }) => {
                // Clear existing timer if any
                if (offlineTimers.has(userId)) {
                    clearTimeout(offlineTimers.get(userId));
                }

                // Set new timer
                const timerId = setTimeout(() => {
                    $wire.dispatch('check-user-offline', {
                        userId
                    });
                    offlineTimers.delete(userId);
                }, offlineDeboune);

                offlineTimers.set(userId, timerId);
            });

            // Set up private channel for user-specific events
            $wire.on('set-user-id', ({
                userId
            }) => {
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
