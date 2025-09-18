<?php

declare(strict_types=1);

namespace App\Livewire\Page;

use App\Events\ConversationUpdated;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserStartedTyping;
use App\Events\UserStoppedTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Chat extends Component
{
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
        abort_if($user->cannot('view', $conversation), 403);

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

            // Listen for forwarded events from NavigationChat
            'navigation-message-received' => 'handleForwardedMessage',
            'navigation-conversation-updated' => 'handleForwardedConversationUpdate',
            'navigation-users-online' => 'updateOnlineUsers',
            'navigation-user-joined' => 'handleForwardedUserJoined',
            'navigation-user-left' => 'handleForwardedUserLeft',
        ];

        // Keep conversation-specific listeners for typing indicators and conversation presence
        if ($this->conversationHash) {
            $listeners[sprintf('echo-private:conversation.%s,MessageSent', $this->conversationHash)] = 'handleIncomingMessage';
            $listeners[sprintf('echo-private:conversation.%s,MessageRead', $this->conversationHash)] = 'handleMessageRead';
            $listeners[sprintf('echo-presence:conversation.%s,UserStartedTyping', $this->conversationHash)] = 'handleUserStartedTyping';
            $listeners[sprintf('echo-presence:conversation.%s,UserStoppedTyping', $this->conversationHash)] = 'handleUserStoppedTyping';
            $listeners[sprintf('echo-presence:conversation.%s,leaving', $this->conversationHash)] = 'handleUserLeavingConversation';
        }

        return $listeners;
    }

    /**
     * Handle incoming message from broadcast.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleIncomingMessage(array $event): void
    {
        if (! $this->selectedConversation || $this->selectedConversation->hash_id !== $this->conversationHash) {
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
     * @param  array<string, mixed>  $event
     */
    public function handleMessageRead(array $event): void
    {
        if (! $this->selectedConversation || $this->selectedConversation->hash_id !== $this->conversationHash) {
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

        $this->selectedConversation->archiveFor($user);

        $this->closeArchiveModal();

        $this->dispatch('conversation-archived')->to('navigation-chat'); // Update navigation dropdown

        $latestConversation = Conversation::query()->latestFor($user)->first();
        if ($latestConversation) {
            $this->switchConversation($latestConversation->hash_id);
        } else {
            $this->selectedConversation = null;
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

        // Find or create conversation (pass the creator as the current user)
        $conversation = Conversation::findOrCreateBetween($user, $otherUser, creator: $user);

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
     * @param  array{user_id: int, user_name: string}  $event
     */
    public function handleUserStartedTyping(array $event): void
    {
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
     * @param  array{user_id: int, user_name: string}  $event
     */
    public function handleUserStoppedTyping(array $event): void
    {
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
        return isset($this->onlineUsers[$userId]) && $this->onlineUsers[$userId];
    }

    /**
     * Get the last seen time for a user.
     */
    public function getUserLastSeen(int $userId): ?string
    {
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
        // Only process if this message is for the current conversation
        if (! $this->selectedConversation || ! isset($event['message']['conversation_hash_id'])) {
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
