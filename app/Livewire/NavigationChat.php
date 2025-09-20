<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NavigationChat extends Component
{
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
        if (! $user) {
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
     * Load all conversation hash IDs for the authenticated user.
     */
    private function loadUserConversationHashes(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        // Load ALL conversations the user is part of (including archived ones)
        // We need this for typing indicators to work properly
        $this->conversationHashes = Conversation::query()
            ->forUser($user)
            ->pluck('hash_id')
            ->toArray();
    }

    /**
     * Fetch recent conversations for the authenticated user.
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
            ->limit(5)
            ->get();
    }

    /**
     * Get the count of unread conversations for the authenticated user.
     */
    private function fetchUnreadCount(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        return Conversation::query()
            ->visibleTo($user)
            ->notArchivedBy($user)
            ->withUnreadMessages($user)
            ->count();
    }

    /**
     * Mark a conversation as read for the authenticated user.
     */
    public function markAsRead(int $conversationId): void
    {
        $user = Auth::user();
        if (! $user) {
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

        // If the conversation is archived for the current user, unarchive it
        if ($conversation->isArchivedBy($user)) {
            $conversation->unarchiveFor($user);
        }

        // If this is a new conversation, update our conversation hashes
        if (! $existingConversation) {
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
        $this->dispatch('navigation-users-online', onlineUsers: $this->onlineUsers)->to(Chat::class);
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
            User::query()->where('id', $event['id'])->update(['last_seen_at' => now()]);
        }

        // Forward updated online users state to Chat component
        $this->dispatch('navigation-user-joined', userId: $event['id'], onlineUsers: $this->onlineUsers)->to(Chat::class);
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
            User::query()->where('id', $userId)->update(['last_seen_at' => now()]);
        }

        // Forward updated online users state to Chat component
        $this->dispatch('navigation-user-left', userId: $userId, onlineUsers: $this->onlineUsers)->to(Chat::class);
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
        $this->dispatch('navigation-message-received', $event)->to(Chat::class);

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
        $this->dispatch('navigation-conversation-updated', $event)->to(Chat::class);

        // Refresh the conversation list when a conversation is updated
        $this->dispatch('$refresh');
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
        $this->dispatch('navigation-typing-started', $event)->to(Chat::class);
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
        $this->dispatch('navigation-typing-stopped', $event)->to(Chat::class);
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
     * Render the navigation chat component.
     */
    public function render(): View
    {
        return view('livewire.navigation-chat', [
            'conversations' => $this->fetchConversations(),
            'unreadCount' => $this->fetchUnreadCount(),
            'searchResults' => $this->fetchSearchResults(),
        ]);
    }
}
