<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
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

        $conversation = Conversation::findOrCreateBetween($user, $otherUser, creator: $user);

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

            $this->dispatch('dropdown-close'); // Close the dropdown

            return;
        }

        $this->redirect(route('chat', ['conversationHash' => $hashId]), navigate: true);
    }

    /**
     * Refresh the component when a conversation is archived.
     */
    #[On('conversation-archived')]
    public function refreshOnArchive(): void
    {
        // This triggers a render.
    }

    /**
     * Refresh the component when a conversation is updated.
     */
    #[On('conversation-updated')]
    public function refreshOnUpdate(): void
    {
        // This triggers a render.
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
