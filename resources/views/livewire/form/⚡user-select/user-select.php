<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
    /**
     * The selected users (array of user IDs).
     *
     * @var array<int>
     */
    #[Modelable]
    public array $selectedUsers = [];

    /**
     * The search query for finding users.
     */
    public string $search = '';

    /**
     * The search results.
     *
     * @var Collection<int, User>
     */
    public Collection $searchResults;

    /**
     * Whether the search dropdown is open.
     */
    public bool $showDropdown = false;

    /**
     * Maximum number of users that can be selected.
     */
    #[Reactive]
    public int $maxUsers = 10;

    /**
     * Placeholder text for the search input.
     */
    #[Reactive]
    public string $placeholder = 'Search for users...';

    /**
     * Label for the field.
     */
    #[Reactive]
    public string $label = 'Authors';

    /**
     * Description for the field.
     */
    #[Reactive]
    public string $description = '';

    /**
     * Users to exclude from search results (e.g., the owner).
     *
     * @var array<int>
     */
    #[Reactive]
    public array $excludeUsers = [];

    /**
     * Mount the component.
     *
     * @param  array<int>  $selectedUsers
     */
    public function mount(array $selectedUsers = []): void
    {
        $this->selectedUsers = $selectedUsers;
        /** @var Collection<int, User> $empty */
        $empty = collect();
        $this->searchResults = $empty;
    }

    /**
     * Perform the user search.
     */
    public function updatedSearch(): void
    {
        $searchTerm = Str::of($this->search)->trim();

        if ($searchTerm->length() < 2) {
            /** @var Collection<int, User> $empty */
            $empty = collect();
            $this->searchResults = $empty;
            $this->showDropdown = false;

            return;
        }

        $query = User::query()
            ->where('name', 'like', $this->search . '%')
            ->whereNotIn('id', array_merge($this->selectedUsers, $this->excludeUsers))
            ->withCount(['mods', 'comments'])
            ->limit(10);

        /** @var Collection<int, User> $results */
        $results = $query->get();
        $this->searchResults = $results;
        $this->showDropdown = $this->searchResults->isNotEmpty();
    }

    /**
     * Add a user to the selected list.
     */
    public function addUser(int $userId): void
    {
        if (count($this->selectedUsers) >= $this->maxUsers) {
            return;
        }

        if (!in_array($userId, $this->selectedUsers)) {
            $this->selectedUsers[] = $userId;
            $this->dispatch('updateAuthorIds', ids: $this->selectedUsers);
        }

        $this->search = '';
        /** @var Collection<int, User> $empty */
        $empty = collect();
        $this->searchResults = $empty;
        $this->showDropdown = false;
    }

    /**
     * Remove a user from the selected list.
     */
    public function removeUser(int $userId): void
    {
        $this->selectedUsers = array_values(array_filter($this->selectedUsers, fn(int $id): bool => $id !== $userId));
        $this->dispatch('updateAuthorIds', ids: $this->selectedUsers);
    }

    /**
     * Get the selected User models.
     *
     * @return Collection<int, User>
     */
    public function getSelectedUsersProperty(): Collection
    {
        if ($this->selectedUsers === []) {
            /** @var Collection<int, User> */
            return collect();
        }

        /** @var Collection<int, User> */
        return User::query()
            ->whereIn('id', $this->selectedUsers)
            ->withCount(['mods', 'comments'])
            ->get();
    }

    /**
     * Close the dropdown when clicking outside.
     */
    public function closeDropdown(): void
    {
        $this->showDropdown = false;
    }
};
