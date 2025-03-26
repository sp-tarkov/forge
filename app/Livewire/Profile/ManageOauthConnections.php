<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ManageOauthConnections extends Component
{
    /**
     * Store the current user.
     */
    #[Locked]
    public User $user;

    /**
     * Controls the confirmation modal visibility.
     */
    public bool $confirmingConnectionDeletion = false;

    /**
     * Stores the ID of the connection to be deleted.
     */
    #[Locked]
    public ?string $selectedConnectionId = null;

    /**
     * Initializes the component by loading the user's OAuth connections.
     */
    public function mount(): void
    {
        $this->user = auth()->user();
    }

    /**
     * Sets up the deletion confirmation.
     */
    public function confirmConnectionDeletion(string $connectionId): void
    {
        $this->confirmingConnectionDeletion = true;
        $this->selectedConnectionId = $connectionId;
    }

    /**
     * Deletes the selected OAuth connection.
     */
    public function deleteConnection(): void
    {
        $connection = $this->user->oauthConnections()->find($this->selectedConnectionId);

        // Ensure the user is authorized to delete the connection.
        $this->authorize('delete', $connection);

        // The user must have a password set before removing an OAuth connection.
        if ($this->user->password === null) {
            $this->addError('password_required', __('You must set a password before removing an OAuth connection.'));
            $this->confirmingConnectionDeletion = false;

            return;
        }

        if ($connection) {
            $connection->delete();

            $this->user->refresh();
            $this->confirmingConnectionDeletion = false;
            $this->selectedConnectionId = null;

            session()->flash('status', __('OAuth connection removed successfully.'));
        } else {
            session()->flash('error', __('OAuth connection not found.'));
        }
    }

    /**
     * Refreshes the user instance.
     */
    #[On('saved')]
    public function refreshUser(): void
    {
        $this->user->refresh();
    }

    /**
     * Renders the component view.
     */
    public function render(): View
    {
        return view('livewire.profile.manage-oauth-connections');
    }
}
