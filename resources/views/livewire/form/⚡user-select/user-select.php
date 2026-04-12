<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * @property Collection<int, User> $searchResults
 */
new class extends Component
{
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
     * Get search results based on the current query.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        $searchTerm = Str::of($this->search)->trim();

        if ($searchTerm->length() < 2) {
            /** @var Collection<int, User> */
            return collect();
        }

        /** @var Collection<int, User> */
        return User::query()
            ->where('name', 'like', $this->search.'%')
            ->whereNotIn('id', array_merge($this->selectedUsers, $this->excludeUsers))
            ->limit(10)
            ->get();
    }

    /**
     * Handle selection changes by dispatching to parent.
     */
    public function updatedSelectedUsers(): void
    {
        $this->dispatch('updateAuthorIds', ids: $this->selectedUsers);
    }
};
