<?php

namespace App\Livewire\User;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class FollowCard extends Component
{
    /**
     * The ID of the user whose profile is being viewed.
     */
    #[Locked]
    public int $profileUserId;

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
     * The user data to display in the card.
     */
    #[Locked]
    public array $display = [];

    /**
     * The limited user data to display in the card.
     */
    #[Locked]
    public array $displayLimit = [];

    /**
     * The maximum number of users to display on the card.
     */
    #[Locked]
    public int $limit = 5;

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
     * The number of users being displayed.
     */
    #[Locked]
    public int $followUsersCount;

    /**
     * Called when the component is initialized.
     */
    public function mount(): void
    {
        $this->profileUser = User::select(['id', 'name', 'profile_photo_path', 'cover_photo_path'])
            ->findOrFail($this->profileUserId);

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
        $this->populateFollowUsers();

        return view('livewire.user.follow-card');
    }

    /**
     * Called when the user follows or unfollows a user.
     */
    #[On('user-follow-change')]
    public function populateFollowUsers(): void
    {
        // Fetch IDs of all users the authenticated user is following.
        $followingIds = collect();
        $authUser = auth()->user();
        if ($authUser) {
            $followingIds = $authUser->following()->pluck('following_id');
        }

        // Load the profile user's followers (or following).
        $users = $this->profileUser->{$this->relationship}()->with([])->get();

        // Count the number of users.
        $this->followUsersCount = $users->count();

        // Load the users to display and whether the authenticated user is following each user.
        $this->display = $users
            ->map(function (User $user) use ($followingIds) {
                return [
                    'user' => $user,
                    'isFollowing' => $followingIds->contains($user->id),
                ];
            })->toArray();

        // Store limited users for the main view.
        $this->displayLimit = collect($this->display)
            ->take($this->limit)
            ->toArray();
    }

    /**
     * Toggle showing the follow dialog.
     */
    public function toggleFollowDialog(): void
    {
        $this->showFollowDialog = ! $this->showFollowDialog;
    }
}
