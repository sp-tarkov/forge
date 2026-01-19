<?php

declare(strict_types=1);

use App\Events\ConversationUpdated;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserBlocked;
use App\Events\UserStartedTyping;
use App\Events\UserStoppedTyping;
use App\Events\UserUnblocked;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {
    /**
     * The conversation hash ID that is injected into the component from the URL.
     */
    public ?string $conversationHash = null;

    /**
     * Controls visibility of the new conversation modal.
     */
    public bool $showNewConversation = false;

    /**
     * Controls visibility of the archive conversation modal.
     */
    public bool $showArchiveModal = false;

    /**
     * Controls visibility of the block user modal.
     */
    public bool $showBlockModal = false;

    /**
     * The reason for blocking a user.
     */
    public string $blockReason = '';

    /**
     * Search query for finding users in the new conversation modal.
     */
    public string $searchUser = '';

    /**
     * The currently selected conversation model instance.
     */
    public ?Conversation $selectedConversation = null;

    /**
     * The message text being composed.
     */
    public string $messageText = '';

    /**
     * Number of messages per page for pagination.
     */
    public int $perPage = 20;

    /**
     * Current number of pages loaded for messages.
     */
    public int $pagesLoaded = 1;

    /**
     * Flag to indicate if there are more messages to load.
     */
    public bool $hasMoreMessages = true;

    /**
     * Array of users currently typing in the conversation.
     *
     * @var array<int, array{id: int, name: string}>
     */
    public array $typingUsers = [];

    /**
     * Track if the current user is typing.
     */
    public bool $isTyping = false;

    /**
     * Array of currently online user IDs.
     *
     * @var array<int, bool>
     */
    public array $onlineUsers = [];

    /**
     * Store all conversation hashes to register listeners for cleanup.
     *
     * @var array<int, string>
     */
    protected array $allConversationHashes = [];

    /**
     * Initialize the component with optional conversation.
     */
    public function boot(): void
    {
        // Only switch conversation if we don't already have one selected or if the hash doesn't match the current selection
        if ($this->conversationHash && (!$this->selectedConversation || $this->selectedConversation->hash_id !== $this->conversationHash)) {
            $this->switchConversation($this->conversationHash);
        } elseif (!$this->selectedConversation && !$this->conversationHash) {
            $this->redirectToLatestIfExists();
        }
    }

    /**
     * Handle the switch-conversation event from navigation.
     */
    public function handleSwitchConversation(string $hashId): void
    {
        $this->switchConversation($hashId);
    }

    /**
     * Switch to a different conversation.
     */
    public function switchConversation(string $hashId): void
    {
        $user = Auth::user();

        $conversation = Conversation::query()
            ->withUserContext($user)
            ->with(['user1', 'user2', 'lastMessage'])
            ->where('hash_id', $hashId)
            ->first();

        abort_if(!$conversation, 404);
        abort_if(!$conversation->hasUser($user), 403);

        $this->selectedConversation = $conversation;
        $this->conversationHash = $hashId;
        $this->typingUsers = [];
        $this->isTyping = false;

        $conversation->markReadBy($user);

        // Broadcast that messages have been read
        broadcast(new MessageRead($conversation, $user))->toOthers();

        $this->pagesLoaded = 1;

        // Join the conversation's presence channel
        $this->dispatch('join-conversation-presence', conversationHash: $hashId);

        // Check if there are more messages than one page
        $totalMessages = Message::query()->where('conversation_id', $conversation->id)->count();
        $this->hasMoreMessages = $totalMessages > $this->perPage;

        // Update URL without reloading
        $this->js(sprintf("window.history.pushState({}, '', '%s')", $conversation->url));

        // Trigger scroll to bottom for new conversation
        $this->dispatch('conversation-switched');
    }

    /**
     * Get listeners for this component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = [
            'switch-conversation' => 'handleSwitchConversation',
            'navigation-message-received' => 'handleForwardedMessage',
            'navigation-conversation-updated' => 'handleForwardedConversationUpdate',
            'navigation-users-online' => 'updateOnlineUsers',
            'navigation-user-joined' => 'handleForwardedUserJoined',
            'navigation-user-left' => 'handleForwardedUserLeft',
            'navigation-typing-started' => 'handleForwardedTypingStarted',
            'navigation-typing-stopped' => 'handleForwardedTypingStopped',
            'user-blocked' => 'handleUserBlocked',
            'user-unblocked' => 'handleUserUnblocked',
        ];

        // Keep conversation-specific listeners for typing indicators and conversation presence
        if ($this->conversationHash) {
            $listeners[sprintf('echo-private:conversation.%s,MessageSent', $this->conversationHash)] = 'handleIncomingMessage';
            $listeners[sprintf('echo-private:conversation.%s,MessageRead', $this->conversationHash)] = 'handleMessageRead';
            $listeners[sprintf('echo-presence:conversation.%s,UserStartedTyping', $this->conversationHash)] = 'handleUserStartedTyping';
            $listeners[sprintf('echo-presence:conversation.%s,UserStoppedTyping', $this->conversationHash)] = 'handleUserStoppedTyping';
            $listeners[sprintf('echo-presence:conversation.%s,leaving', $this->conversationHash)] = 'handleUserLeavingConversation';
        }

        // Register listeners for all conversations the user is part of
        $user = Auth::user();
        if ($user) {
            $conversationHashes = Conversation::query()->forUser($user)->pluck('hash_id')->toArray();
            foreach ($conversationHashes as $hash) {
                if ($hash !== $this->conversationHash) {
                    $listeners[sprintf('echo-private:conversation.%s,MessageSent', $hash)] = 'handleIncomingMessage';
                    $listeners[sprintf('echo-private:conversation.%s,MessageRead', $hash)] = 'handleMessageRead';
                    $listeners[sprintf('echo-presence:conversation.%s,UserStartedTyping', $hash)] = 'handleUserStartedTyping';
                    $listeners[sprintf('echo-presence:conversation.%s,UserStoppedTyping', $hash)] = 'handleUserStoppedTyping';
                    $listeners[sprintf('echo-presence:conversation.%s,leaving', $hash)] = 'handleUserLeavingConversation';
                }
            }
        }

        return $listeners;
    }

    /**
     * Handle incoming message from broadcast.
     *
     * @param  array<string, mixed>|bool  $event
     */
    public function handleIncomingMessage(array|bool $event): void
    {
        // Handle authorization failure or empty event
        if (is_bool($event) || !$this->selectedConversation || !$this->conversationHash) {
            return;
        }

        // Only process if this is for the current conversation
        if ($this->selectedConversation->hash_id !== $this->conversationHash) {
            return;
        }

        // Clear typing indicator for the sender since they sent a message
        if (isset($event['message']['user_id'])) {
            unset($this->typingUsers[$event['message']['user_id']]);
        }

        $user = Auth::user();

        // Mark the conversation as read since user is actively viewing it
        if ($user) {
            $this->selectedConversation->markReadBy($user);

            // Broadcast that messages have been read
            broadcast(new MessageRead($this->selectedConversation, $user))->toOthers();
        }

        // Refresh messages to include the new one
        $this->dispatch('messages-updated');
    }

    /**
     * Handle message read event from broadcast.
     *
     * @param  array<string, mixed>|bool  $event
     */
    public function handleMessageRead(array|bool $event): void
    {
        // Handle authorization failure or empty event
        if (is_bool($event) || !$this->selectedConversation || !$this->conversationHash) {
            return;
        }

        // Only process if this is for the current conversation
        if ($this->selectedConversation->hash_id !== $this->conversationHash) {
            return;
        }

        // Refresh to update read status
        $this->dispatch('messages-updated');
    }

    /**
     * Navigate to a specific conversation.
     */
    public function redirectToConversation(Conversation|string $conversation): void
    {
        if (!$conversation instanceof Conversation) {
            $conversation = Conversation::query()->where('hash_id', $conversation)->first();
        }

        if (!$conversation || !$conversation->hash_id) {
            return;
        }

        $this->redirect($conversation->url, navigate: true);
    }

    /**
     * Send a new message in the current conversation. Creates a new message, clears the input field, and refreshes the
     * conversation to display the new message.
     */
    public function sendMessage(): void
    {
        if (!$this->selectedConversation || empty(mb_trim($this->messageText))) {
            return;
        }

        $user = Auth::user();

        abort_if($user->cannot('sendMessage', $this->selectedConversation), 403);

        $user->updateLastSeen();

        $message = $this->selectedConversation->messages()->create([
            'user_id' => $user->id,
            'content' => mb_trim($this->messageText),
        ]);

        $this->messageText = '';
        $this->stopTyping();

        $this->selectedConversation->unarchiveForAllUsers();
        $this->selectedConversation->refresh();

        // Broadcast the message to other users
        broadcast(new MessageSent($message, $user))->toOthers();
        broadcast(new ConversationUpdated($this->selectedConversation))->toOthers();

        $this->dispatch('messages-updated'); // Trigger scroll
        $this->dispatch('conversation-updated')->to('navigation-chat'); // Update navigation dropdown
    }

    /**
     * Open the archive conversation modal.
     */
    public function openArchiveModal(): void
    {
        $this->showArchiveModal = true;
    }

    /**
     * Close the archive conversation modal.
     */
    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
    }

    /**
     * Archive the current conversation for the current user.
     */
    public function archiveConversation(): void
    {
        if ($this->selectedConversation === null) {
            return;
        }

        $user = Auth::user();

        // Store the current conversation hash before clearing
        $oldConversationHash = $this->conversationHash;

        $this->selectedConversation->archiveFor($user);

        $this->closeArchiveModal();

        $this->dispatch('conversation-archived')->to('navigation-chat'); // Update navigation dropdown

        // Always leave the old presence channel before switching or clearing
        if ($oldConversationHash) {
            $this->dispatch('leave-conversation-presence', conversationHash: $oldConversationHash);
        }

        $latestConversation = Conversation::query()->latestFor($user)->first();
        if ($latestConversation) {
            $this->switchConversation($latestConversation->hash_id);
        } else {
            // Reset the component completely
            $this->reset(['selectedConversation', 'conversationHash', 'messageText', 'typingUsers', 'isTyping', 'pagesLoaded', 'hasMoreMessages']);

            // Reset to defaults
            $this->pagesLoaded = 1;
            $this->hasMoreMessages = true;

            // Update URL to remove conversation hash
            $this->js("window.history.pushState({}, '', '" . route('chat') . "')");
        }
    }

    /**
     * Load more messages when the user scrolls up.
     */
    public function loadMoreMessages(): void
    {
        if (!$this->selectedConversation || !$this->hasMoreMessages) {
            return;
        }

        $this->pagesLoaded++;
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
     * Start a new conversation. Finds or creates a conversation with the specified user and navigates to it.
     */
    public function startConversation(int $userId): void
    {
        $user = Auth::user();
        $otherUser = User::query()->find($userId);

        if (!$otherUser || $otherUser->id === $user->id) {
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

        // If the conversation is archived for the current user, try to unarchive it
        if ($conversation->isArchivedBy($user)) {
            if ($user->can('unarchive', $conversation)) {
                $conversation->unarchiveFor($user);
                $this->dispatch('conversation-updated')->to('navigation-chat');
            } else {
                flash()->error('Cannot start conversation with this user.');
                $this->closeNewConversationModal();

                return;
            }
        }

        // If this is a new conversation, notify NavigationChat to update its conversation hashes
        if (!$existingConversation) {
            $this->dispatch('conversation-created')->to('navigation-chat');
        }

        $this->closeNewConversationModal();

        $this->switchConversation($conversation->hash_id);
    }

    /**
     * Handle typing input. Called when user types in the message field.
     */
    public function handleTyping(): void
    {
        if (!$this->selectedConversation || !Auth::check()) {
            return;
        }

        $user = Auth::user();

        if (!$this->isTyping && $user) {
            $this->isTyping = true;
            broadcast(new UserStartedTyping($this->selectedConversation, $user))->toOthers();
        }
    }

    /**
     * Stop typing. Called when timer expires or user stops typing.
     */
    public function stopTyping(): void
    {
        if (!$this->selectedConversation || !$this->isTyping || !Auth::check()) {
            return;
        }

        $user = Auth::user();

        if ($user) {
            $this->isTyping = false;
            broadcast(new UserStoppedTyping($this->selectedConversation, $user))->toOthers();
        }
    }

    /**
     * Handle user started typing event from broadcast.
     *
     * @param  array{user_id: int, user_name: string}|bool  $event
     */
    public function handleUserStartedTyping(array|bool $event): void
    {
        // Handle authorization failure or when conversation is not selected
        if (is_bool($event) || !$this->selectedConversation || !$this->conversationHash) {
            return;
        }

        // Don't show typing indicator for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        if (!isset($this->typingUsers[$event['user_id']])) {
            $this->typingUsers[$event['user_id']] = [
                'id' => $event['user_id'],
                'name' => $event['user_name'],
            ];
        }
    }

    /**
     * Handle user stopped typing event from broadcast.
     *
     * @param  array{user_id: int, user_name: string}|bool  $event
     */
    public function handleUserStoppedTyping(array|bool $event): void
    {
        // Handle authorization failure or when conversation is not selected
        if (is_bool($event) || !$this->selectedConversation || !$this->conversationHash) {
            return;
        }

        // Don't process for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        unset($this->typingUsers[$event['user_id']]);
    }

    /**
     * Handle user leaving the conversation presence channel.
     *
     * @param  array{id: int, name: string, profile_photo_url: string}|bool  $user
     */
    public function handleUserLeavingConversation(array|bool $user): void
    {
        // Handle the case where we receive a boolean (authorization failure)
        if (is_bool($user)) {
            return;
        }

        unset($this->typingUsers[$user['id']]);
    }

    /**
     * Check if a user is online.
     */
    public function isUserOnline(int $userId): bool
    {
        // Don't show online status if blocked
        $currentUser = Auth::user();
        if ($currentUser) {
            $otherUser = User::query()->find($userId);
            if ($otherUser && $currentUser->isBlockedMutually($otherUser)) {
                return false;
            }
        }

        return isset($this->onlineUsers[$userId]) && $this->onlineUsers[$userId];
    }

    /**
     * Get the last seen time for a user.
     */
    public function getUserLastSeen(int $userId): ?string
    {
        // Check if users have blocked each other
        $currentUser = Auth::user();
        if ($currentUser) {
            $otherUser = User::query()->find($userId);
            if ($otherUser && $currentUser->isBlockedMutually($otherUser)) {
                return __('Not available');
            }
        }

        if ($this->isUserOnline($userId)) {
            return null;
        }

        $user = User::query()->select('last_seen_at')->find($userId);
        if ($user && $user->last_seen_at) {
            $diffInMinutes = $user->last_seen_at->diffInMinutes(now());

            return match (true) {
                $diffInMinutes < 1 => __('Last seen just now'),
                $diffInMinutes >= 1 && $diffInMinutes < 2 => __('Last seen 1 minute ago'),
                $diffInMinutes < 60 => __('Last seen :minutes minutes ago', ['minutes' => (int) $diffInMinutes]),
                $diffInMinutes < 120 => __('Last seen 1 hour ago'),
                $diffInMinutes < 1440 => __('Last seen :hours hours ago', ['hours' => (int) floor($diffInMinutes / 60)]),
                default => __('Last seen :date', ['date' => $diffInMinutes]),
            };
        }

        return __('Offline');
    }

    /**
     * Handle forwarded message event from NavigationChat.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleForwardedMessage(array $event): void
    {
        // Check if we have a conversation hash in the event
        if (!isset($event['message']['conversation_hash_id'])) {
            return;
        }

        // If no conversation is selected but we receive a message, it might be for an archived conversation
        if ($this->selectedConversation === null) {
            // Check if this is a conversation we're part of
            $user = Auth::user();
            $conversation = Conversation::query()->where('hash_id', $event['message']['conversation_hash_id'])->forUser($user)->first();

            if ($conversation) {
                $this->switchConversation($conversation->hash_id);
            }

            return;
        }

        if ($this->selectedConversation->hash_id === $event['message']['conversation_hash_id']) {
            // Clear typing indicator for the sender
            if (isset($event['message']['user_id'])) {
                unset($this->typingUsers[$event['message']['user_id']]);
            }

            // Refresh messages to include the new one
            $this->dispatch('messages-updated');
        }
    }

    /**
     * Handle forwarded conversation update event from NavigationChat.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleForwardedConversationUpdate(array $event): void
    {
        // Refresh if this is our conversation
        if ($this->selectedConversation && isset($event['conversation_id']) && $this->selectedConversation->id === $event['conversation_id']) {
            $this->dispatch('$refresh');
        }
    }

    /**
     * Update online users from NavigationChat.
     *
     * @param  array<int, bool>  $onlineUsers
     */
    public function updateOnlineUsers(array $onlineUsers): void
    {
        $this->onlineUsers = $onlineUsers;
    }

    /**
     * Handle forwarded user joined event from NavigationChat.
     *
     * @param  array<int, bool>  $onlineUsers
     */
    public function handleForwardedUserJoined(int $userId, array $onlineUsers): void
    {
        $this->onlineUsers = $onlineUsers;
    }

    /**
     * Handle forwarded user left event from NavigationChat.
     *
     * @param  array<int, bool>  $onlineUsers
     */
    public function handleForwardedUserLeft(int $userId, array $onlineUsers): void
    {
        $this->onlineUsers = $onlineUsers;
        unset($this->typingUsers[$userId]);
    }

    /**
     * Handle forwarded typing started event from NavigationChat.
     *
     * @param  array{conversation_hash: string, user_id: int, user_name: string}  $event
     */
    public function handleForwardedTypingStarted(array $event): void
    {
        // Only process if this is for the current conversation
        if (!$this->selectedConversation || $this->selectedConversation->hash_id !== $event['conversation_hash']) {
            return;
        }

        // Don't show typing indicator for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        if (!isset($this->typingUsers[$event['user_id']])) {
            $this->typingUsers[$event['user_id']] = [
                'id' => $event['user_id'],
                'name' => $event['user_name'],
            ];
        }
    }

    /**
     * Handle forwarded typing stopped event from NavigationChat.
     *
     * @param  array{conversation_hash: string, user_id: int, user_name: string}  $event
     */
    public function handleForwardedTypingStopped(array $event): void
    {
        // Only process if this is for the current conversation
        if (!$this->selectedConversation || $this->selectedConversation->hash_id !== $event['conversation_hash']) {
            return;
        }

        // Don't process for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        unset($this->typingUsers[$event['user_id']]);
    }

    /**
     * Toggle notification settings for the current conversation.
     */
    public function toggleNotifications(): void
    {
        if ($this->selectedConversation === null) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $isEnabled = $this->selectedConversation->toggleNotificationForUser($user);

        if ($isEnabled) {
            flash()->success('Notifications enabled for this conversation');
        } else {
            flash()->success('Notifications disabled for this conversation');
        }
    }

    /**
     * Check if notifications are enabled for the current conversation.
     */
    public function isNotificationEnabled(): bool
    {
        if ($this->selectedConversation === null) {
            return true;
        }

        $user = Auth::user();
        if (!$user) {
            return true;
        }

        return $this->selectedConversation->isNotificationEnabledForUser($user);
    }

    /**
     * Open the block user modal.
     */
    public function openBlockModal(): void
    {
        $this->blockReason = '';
        $this->showBlockModal = true;
    }

    /**
     * Close the block user modal.
     */
    public function closeBlockModal(): void
    {
        $this->blockReason = '';
        $this->showBlockModal = false;
    }

    /**
     * Get whether the current user has blocked the other user.
     */
    #[Computed]
    public function isUserBlocked(): bool
    {
        if (!$this->selectedConversation || !$this->selectedConversation->other_user) {
            return false;
        }

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasBlocked($this->selectedConversation->other_user);
    }

    /**
     * Get whether the conversation is blocked (either user blocked the other).
     */
    #[Computed]
    public function isConversationBlocked(): bool
    {
        if (!$this->selectedConversation || !$this->selectedConversation->other_user) {
            return false;
        }

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->isBlockedMutually($this->selectedConversation->other_user);
    }

    /**
     * Confirm blocking or unblocking the user.
     */
    public function confirmBlock(): void
    {
        if (!$this->selectedConversation || !$this->selectedConversation->other_user) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $otherUser = $this->selectedConversation->other_user;

        if ($this->isUserBlocked()) {
            // Unblock the user
            $user->unblock($otherUser);
            flash()->success('User unblocked successfully');

            // Broadcast the unblock event for real-time updates
            broadcast(new UserUnblocked($user, $otherUser))->toOthers();
            $this->dispatch('user-unblocked', userId: $otherUser->id)->to('navigation-chat');
        } else {
            // Block the user
            $user->block($otherUser, $this->blockReason ?: null);
            flash()->success('User blocked successfully');

            // Broadcast the block event for real-time updates
            broadcast(new UserBlocked($user, $otherUser))->toOthers();
            $this->dispatch('user-blocked', userId: $otherUser->id)->to('navigation-chat');
        }

        $this->closeBlockModal();
        $this->dispatch('$refresh');
    }

    /**
     * Handle user blocked event.
     */
    public function handleUserBlocked(int $userId): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Handle user unblocked event.
     */
    public function handleUserUnblocked(int $userId): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'conversations' => $this->fetchConversations(),
            'messages' => $this->fetchMessages(),
            'searchResults' => $this->fetchSearchResults(),
        ];
    }

    /**
     * Redirect to the latest conversation if one exists.
     */
    private function redirectToLatestIfExists(): void
    {
        $user = Auth::user();

        $latestConversation = Conversation::query()->latestFor($user)->whereNotNull('hash_id')->first();

        if ($latestConversation) {
            $this->redirectToConversation($latestConversation);
        }
    }

    /**
     * Get all visible conversations for the authenticated user.
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
            ->whereNotNull('hash_id')
            ->withUserContext($user)
            ->withUnreadCount($user)
            ->with(['user1', 'user2', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all messages for the selected conversation.
     *
     * @return Collection<int, Message>
     **/
    private function fetchMessages(): Collection
    {
        if ($this->selectedConversation === null) {
            return new Collection();
        }

        $user = Auth::user();

        if (!$user) {
            return new Collection();
        }

        // Reload the conversation with user context for accessors
        $conversation = Conversation::query()
            ->withUserContext($user)
            ->with(['user1', 'user2'])
            ->find($this->selectedConversation->id);

        if ($conversation) {
            $this->selectedConversation = $conversation;
        }

        // Get a total count to determine if there are more messages
        $totalMessages = Message::query()->where('conversation_id', $this->selectedConversation->id)->count();

        $messagesPerPage = $this->perPage * $this->pagesLoaded;
        $this->hasMoreMessages = $totalMessages > $messagesPerPage;

        // Fetch messages with pagination.
        return Message::query()
            ->where('conversation_id', $this->selectedConversation->id)
            ->withUserContext($user)
            ->with(['user', 'reads'])
            ->orderBy('created_at', 'desc')
            ->limit($messagesPerPage)
            ->get()
            ->reverse()
            ->values();
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
            wire:click="openNewConversationModal"
        >
            {{ __('New Conversation') }}
        </flux:button>
    </div>

    {{-- Chat Interface --}}
    <div
        class="flex gap-4 min-h-[400px] max-h-[80vh]"
        wire:key="chat-interface"
    >
        {{-- Left Column: Conversations List --}}
        <div
            class="w-80 bg-gray-100 dark:bg-gray-900 rounded-lg border border-gray-300 dark:border-gray-800 shadow-sm overflow-hidden min-h-[400px] max-h-[600px] flex flex-col"
            wire:key="conversations-list"
        >
            {{-- Conversations List --}}
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $conversation)
                    @if ($conversation->other_user)
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
                                @if ($this->isUserOnline($conversation->other_user->id))
                                    <span
                                        class="absolute bottom-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"
                                    ></span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0 text-left">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="font-medium truncate">
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
                                        <div class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                            {{ $conversation->lastMessage->created_at->dynamicFormat(includeTime: false) }}
                                        </div>
                                    @endif
                                </div>
                                @if ($conversation->lastMessage)
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
                        <flux:icon
                            name="chat-bubble-left-right"
                            class="w-16 h-16 text-gray-400 dark:text-gray-600 mb-4"
                        />
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
        <div
            class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-300 dark:border-gray-800 shadow-sm overflow-hidden min-h-[400px] max-h-[600px]"
            wire:key="conversation-{{ $selectedConversation?->id ?? 'none' }}"
        >
            @if ($selectedConversation)
                {{-- Conversation Header --}}
                <div
                    class="p-4 border-b border-gray-300 dark:border-gray-800 flex items-center justify-between bg-gray-100 dark:bg-gray-900">
                    @if ($selectedConversation->other_user)
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
                                <div class="font-medium">
                                    <x-user-name :user="$selectedConversation->other_user" />
                                </div>
                                <div class="text-xs">
                                    @if ($this->isUserOnline($selectedConversation->other_user->id))
                                        <span class="text-green-500 dark:text-green-400">{{ __('Online') }}</span>
                                    @else
                                        <span
                                            class="text-gray-500 dark:text-gray-400">{{ $this->getUserLastSeen($selectedConversation->other_user->id) }}</span>
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
                                <flux:menu.separator />
                                @if ($this->isUserBlocked)
                                    <flux:menu.item
                                        icon="shield-check"
                                        wire:click="openBlockModal"
                                    >{{ __('Unblock User') }}</flux:menu.item>
                                @else
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
                    class="flex-1 overflow-y-auto p-4 space-y-4 bg-gradient-to-b from-gray-100/50 to-gray-50 dark:from-gray-950/50 dark:to-gray-950"
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
                                <div
                                    class="px-4 py-2 bg-cyan-50 dark:bg-cyan-950 border border-cyan-200 dark:border-cyan-800 rounded-lg">
                                    <svg
                                        class="inline-block animate-spin h-4 w-4 text-cyan-600 dark:text-cyan-400 mr-2"
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
                                        class="text-sm font-medium text-cyan-700 dark:text-cyan-300">{{ __('Loading earlier messages...') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- No more messages indicator --}}
                    @if (!$hasMoreMessages && count($messages) > 0)
                        <div class="flex justify-center py-4">
                            <div
                                class="px-4 py-2 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
                                <span
                                    class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Beginning of conversation') }}</span>
                            </div>
                        </div>
                    @endif

                    @forelse($messages as $index => $message)
                        <div wire:key="message-{{ $message->id }}">

                            {{-- Show separator after the last newly loaded message --}}
                            @if ($pagesLoaded > 1 && $index === $perPage)
                                <div
                                    class="flex items-center gap-3 py-2 mb-4"
                                    wire:key="separator-current"
                                >
                                    <div class="flex-1 h-px bg-gray-300 dark:bg-gray-700"></div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 px-2">Recently loaded
                                        above</span>
                                    <div class="flex-1 h-px bg-gray-300 dark:bg-gray-700"></div>
                                </div>
                            @endif

                            @if ($message->is_mine)
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
                                                <div class="text-sm user-markdown-message max-w-none">
                                                    {!! $message->content_html !!}</div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">
                                                {{ $message->created_at->dynamicFormat() }}
                                                @if ($message->is_read)
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
                                            <div
                                                class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm">
                                                <div class="text-sm user-markdown-message max-w-none">
                                                    {!! $message->content_html !!}</div>
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
                            <flux:icon
                                name="chat-bubble-left-right"
                                class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-3"
                            />
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
                    class="px-4 pb-2 text-sm text-gray-500 dark:text-gray-400 italic"
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
                    <div
                        class="p-4 border-t border-gray-300 dark:border-gray-800 bg-gray-100 dark:bg-gray-900 text-center">
                        <div class="text-gray-500 dark:text-gray-400">
                            <flux:icon
                                name="shield-exclamation"
                                class="w-8 h-8 mx-auto mb-2"
                            />
                            <p class="text-sm font-medium">{{ __('You can\'t send messages in this conversation') }}
                            </p>
                            <p class="text-xs mt-1">{{ __('One or both users have blocked this conversation') }}</p>
                        </div>
                    </div>
                @else
                    <div
                        class="p-4 border-t border-gray-300 dark:border-gray-800 bg-gray-100 dark:bg-gray-900"
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
                        <div
                            x-show="showCounter"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform translate-y-1"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-1"
                            class="text-xs text-right mt-2"
                            :class="counterColor"
                        >
                            <span x-text="charCount"></span>/500 characters
                        </div>
                    </div>
                @endif
            @else
                {{-- No conversation selected --}}
                <div class="flex flex-col items-center justify-center h-full text-center p-8">
                    <flux:icon
                        name="chat-bubble-left-right"
                        class="w-20 h-20 text-gray-400 dark:text-gray-600 mb-4"
                    />
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
    <flux:modal
        wire:model.live="showArchiveModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="archive-box"
                        class="w-8 h-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
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
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    {{ __('Archive') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Block User Modal --}}
    <flux:modal
        wire:model.live="showBlockModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    @if ($this->isUserBlocked)
                        <flux:icon
                            name="shield-check"
                            class="w-8 h-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Unblock User') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Allow messages and interactions again') }}
                            </flux:text>
                        </div>
                    @else
                        <flux:icon
                            name="shield-exclamation"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Block User') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Prevent messages and interactions') }}
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                @if ($this->isUserBlocked())
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to unblock this user? You will be able to send and receive messages again.') }}
                    </flux:text>
                @else
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Are you sure you want to block this user?') }}
                    </flux:text>

                    <div class="space-y-2">
                        <flux:text class="text-gray-900 dark:text-gray-100 text-sm font-medium">
                            {{ __('What happens when you block someone:') }}
                        </flux:text>
                        <ul class="text-gray-700 dark:text-gray-300 text-sm list-disc list-inside space-y-1 ml-2">
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
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
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

    {{-- New Conversation Modal --}}
    <x-new-conversation-modal
        :show-modal="'showNewConversation'"
        :search-user="$searchUser"
        :search-results="$searchResults"
    />

    {{-- Presence channel (typing) --}}
    @script
        <script>
            let currentConversationChannel = null;
            $wire.on('join-conversation-presence', ({
                conversationHash
            }) => {
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

            // Handle leaving presence channel when archiving or clearing conversation
            $wire.on('leave-conversation-presence', ({
                conversationHash
            }) => {
                if (!window.Echo || !conversationHash) return;
                if (currentConversationChannel === `presence.conversation.${conversationHash}`) {
                    window.Echo.leave(currentConversationChannel);
                    currentConversationChannel = null;
                }
            });

            // Listen for blocking events on the user's private channel
            const currentUserId = {{ auth()->check() ? auth()->id() : 'null' }};
            if (window.Echo && currentUserId) {
                window.Echo.private(`user.${currentUserId}`)
                    .listen('UserBlocked', (e) => {
                        $wire.handleUserBlocked(e.blocker_id);
                    })
                    .listen('UserUnblocked', (e) => {
                        $wire.handleUserUnblocked(e.unblocker_id);
                    });
            }
        </script>
    @endscript
</div>
