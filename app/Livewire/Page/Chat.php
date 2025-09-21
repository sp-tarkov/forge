<?php

declare(strict_types=1);

namespace App\Livewire\Page;

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
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Chat extends Component
{
    /**
     * The conversation hash ID that is injected into the component from the URL.
     */
    public ?string $conversationHash = null;

    /**
     * Store all conversation hashes to register listeners for cleanup.
     *
     * @var array<int, string>
     */
    protected array $allConversationHashes = [];

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
     * Initialize the component with optional conversation.
     */
    public function boot(): void
    {
        // Only switch conversation if we don't already have one selected or if the hash doesn't match the current selection
        if ($this->conversationHash && (! $this->selectedConversation || $this->selectedConversation->hash_id !== $this->conversationHash)) {
            $this->switchConversation($this->conversationHash);
        } elseif (! $this->selectedConversation && ! $this->conversationHash) {
            $this->redirectToLatestIfExists();
        }
    }

    /**
     * Redirect to the latest conversation if one exists.
     */
    private function redirectToLatestIfExists(): void
    {
        $user = Auth::user();

        $latestConversation = Conversation::query()->latestFor($user)->first();
        if ($latestConversation) {
            $this->redirectToConversation($latestConversation->hash_id);
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

        abort_if(! $conversation, 404);
        abort_if(! $conversation->hasUser($user), 403);

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
        $totalMessages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->count();
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
            $conversationHashes = Conversation::query()
                ->forUser($user)
                ->pluck('hash_id')
                ->toArray();
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
        if (is_bool($event) || ! $this->selectedConversation || ! $this->conversationHash) {
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
        if (is_bool($event) || ! $this->selectedConversation || ! $this->conversationHash) {
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
        if (! $conversation instanceof Conversation) {
            $conversation = Conversation::query()->where('hash_id', $conversation)->first();
        }

        $this->redirect($conversation->url, navigate: true);
    }

    /**
     * Send a new message in the current conversation. Creates a new message, clears the input field, and refreshes the
     * conversation to display the new message.
     */
    public function sendMessage(): void
    {
        if (! $this->selectedConversation || empty(trim($this->messageText))) {
            return;
        }

        $user = Auth::user();

        abort_if($user->cannot('sendMessage', $this->selectedConversation), 403);

        $user->updateLastSeen();

        $message = $this->selectedConversation->messages()->create([
            'user_id' => $user->id,
            'content' => trim($this->messageText),
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
            $this->reset([
                'selectedConversation',
                'conversationHash',
                'messageText',
                'typingUsers',
                'isTyping',
                'pagesLoaded',
                'hasMoreMessages',
            ]);

            // Reset to defaults
            $this->pagesLoaded = 1;
            $this->hasMoreMessages = true;

            // Update URL to remove conversation hash
            $this->js("window.history.pushState({}, '', '".route('chat')."')");
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

        if (! $user) {
            return new Collection;
        }

        return Conversation::query()
            ->visibleTo($user)
            ->notArchivedBy($user)
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
            return new Collection;
        }

        $user = Auth::user();

        if (! $user) {
            return new Collection;
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
        $totalMessages = Message::query()
            ->where('conversation_id', $this->selectedConversation->id)
            ->count();

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
     * Load more messages when the user scrolls up.
     */
    public function loadMoreMessages(): void
    {
        if (! $this->selectedConversation || ! $this->hasMoreMessages) {
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

        if (! $otherUser || $otherUser->id === $user->id) {
            return;
        }

        $existingConversation = Conversation::query()
            ->where(function ($query) use ($user, $otherUser): void {
                $query->where('user1_id', $user->id)->where('user2_id', $otherUser->id);
            })->orWhere(function ($query) use ($user, $otherUser): void {
                $query->where('user1_id', $otherUser->id)->where('user2_id', $user->id);
            })->first();

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
        if (! $existingConversation) {
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
        if (! $this->selectedConversation || ! Auth::check()) {
            return;
        }

        $user = Auth::user();

        if (! $this->isTyping && $user) {
            $this->isTyping = true;
            broadcast(new UserStartedTyping($this->selectedConversation, $user))->toOthers();
        }
    }

    /**
     * Stop typing. Called when timer expires or user stops typing.
     */
    public function stopTyping(): void
    {
        if (! $this->selectedConversation || ! $this->isTyping || ! Auth::check()) {
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
        if (is_bool($event) || ! $this->selectedConversation || ! $this->conversationHash) {
            return;
        }

        // Don't show typing indicator for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        if (! isset($this->typingUsers[$event['user_id']])) {
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
        if (is_bool($event) || ! $this->selectedConversation || ! $this->conversationHash) {
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
        if (! isset($event['message']['conversation_hash_id'])) {
            return;
        }

        // If no conversation is selected but we receive a message, it might be for an archived conversation
        if ($this->selectedConversation === null) {
            // Check if this is a conversation we're part of
            $user = Auth::user();
            $conversation = Conversation::query()
                ->where('hash_id', $event['message']['conversation_hash_id'])
                ->forUser($user)
                ->first();

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
        if ($this->selectedConversation && isset($event['conversation_id']) &&
            $this->selectedConversation->id === $event['conversation_id']) {
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
     * Get search results for finding users to start conversations with.
     *
     * @return Collection<int, User>
     */
    private function fetchSearchResults(): Collection
    {
        if (empty($this->searchUser)) {
            return new Collection;
        }

        return User::query()->conversationSearch(Auth::user(), $this->searchUser)->get();
    }

    /**
     * Handle forwarded typing started event from NavigationChat.
     *
     * @param  array{conversation_hash: string, user_id: int, user_name: string}  $event
     */
    public function handleForwardedTypingStarted(array $event): void
    {
        // Only process if this is for the current conversation
        if (! $this->selectedConversation || $this->selectedConversation->hash_id !== $event['conversation_hash']) {
            return;
        }

        // Don't show typing indicator for current user
        if ($event['user_id'] === Auth::id()) {
            return;
        }

        if (! isset($this->typingUsers[$event['user_id']])) {
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
        if (! $this->selectedConversation || $this->selectedConversation->hash_id !== $event['conversation_hash']) {
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
        if (! $user) {
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
        if (! $user) {
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
        if (! $this->selectedConversation || ! $this->selectedConversation->other_user) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
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
        if (! $this->selectedConversation || ! $this->selectedConversation->other_user) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->isBlockedMutually($this->selectedConversation->other_user);
    }

    /**
     * Confirm blocking or unblocking the user.
     */
    public function confirmBlock(): void
    {
        if (! $this->selectedConversation || ! $this->selectedConversation->other_user) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
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
     * Render the chat component view.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.chat', [
            'conversations' => $this->fetchConversations(),
            'messages' => $this->fetchMessages(),
            'searchResults' => $this->fetchSearchResults(),
        ]);
    }
}
