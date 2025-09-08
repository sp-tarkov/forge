<?php

declare(strict_types=1);

namespace App\Livewire\Page;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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
    #[On('switch-conversation')]
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

        $conversation->markReadBy($user);

        $this->pagesLoaded = 1;

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

        $this->selectedConversation->messages()->create([
            'user_id' => $user->id,
            'content' => trim($this->messageText),
        ]);

        $this->messageText = '';

        $this->selectedConversation->unarchiveForAllUsers();
        $this->selectedConversation->refresh();

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

        // Fetch messages with pagination; get the latest messages and show them in chronological order
        // We always fetch the N most recent messages where N = perPage * pagesLoaded
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
