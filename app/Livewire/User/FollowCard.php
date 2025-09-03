<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FollowCard extends Component
{
    /**
     * The type of user follow relationship to display.
     * Currently, either "followers" or "following".
     */
    #[Locked]
    public string $relationship;

    /**
     * The title of the card.
     */
    public string $title;

    /**
     * The message to display when there are no results.
     */
    public string $emptyMessage;

    /**
     * The title of the dialog.
     */
    public string $dialogTitle;

    /**
     * The maximum number of users to display on the card.
     */
    #[Locked]
    public int $limit = 4;

    /**
     * Whether to show all users in a model dialog.
     */
    public bool $showFollowDialog = false;

    /**
     * The user whose profile is being viewed.
     */
    #[Locked]
    public User $profileUser;

    /**
     * A collection of user IDs that the auth user follows.
     *
     * @var Collection<int, int>
     */
    public Collection $authFollowIds;

    /**
     * The profile user's followers (or following).
     *
     * @var Collection<int, User>
     */
    public Collection $followUsers;

    /**
     * The events the component should listen for.
     *
     * @var array<string, string>
     */
    protected $listeners = ['auth-follow-change' => '$refresh'];

    /**
     * The number of users being displayed.
     */
    #[Computed]
    public function followUsersCount(): int
    {
        return $this->followUsers->count();
    }

    /**
     * Called when the component is initialized.
     */
    public function mount(): void
    {
        $this->setTitle();
        $this->setEmptyMessage();
        $this->setDialogTitle();
    }

    /**
     * Set the title of the card based on the relationship.
     */
    private function setTitle(): void
    {
        $this->title = match ($this->relationship) {
            'followers' => __('Followers'),
            'following' => __('Following'),
            default => __('Users'),
        };
    }

    /**
     * Set the empty message based on the relationship.
     */
    private function setEmptyMessage(): void
    {
        $this->emptyMessage = match ($this->relationship) {
            'followers' => __('No followers yet.'),
            'following' => __('Not yet following anyone.'),
            default => __('No users found.'),
        };
    }

    /**
     * Set the dialog title based on the relationship.
     */
    private function setDialogTitle(): void
    {
        $this->dialogTitle = match ($this->relationship) {
            'followers' => 'User :name has these followers:',
            'following' => 'User :name is following:',
            default => 'Users:',
        };
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        // Update the collection of profile user's followers (or following).
        $this->followUsers = $this->profileUser->{$this->relationship}()->get();

        return view('livewire.user.follow-card');
    }

    /**
     * Toggle showing the follow dialog.
     */
    public function toggleFollowDialog(): void
    {
        $this->showFollowDialog = ! $this->showFollowDialog;
    }

    /**
     * Follow a user.
     */
    public function followUser(int $userId): void
    {
        auth()->user()->follow($userId);

        // Update the authFollowIds collection
        $this->authFollowIds = $this->authFollowIds->push($userId);

        $this->dispatch('user-follow-change');
    }

    /**
     * Unfollow a user.
     */
    public function unfollowUser(int $userId): void
    {
        auth()->user()->unfollow($userId);

        // Update the authFollowIds collection
        $this->authFollowIds = $this->authFollowIds->reject(fn (int $id): bool => $id === $userId);

        $this->dispatch('user-follow-change');
    }
}
