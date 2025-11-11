<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Component;

class BlockButton extends Component
{
    public User $user;

    public bool $isBlocked = false;

    public bool $showModal = false;

    public ?string $blockReason = null;

    /**
     * Initialize the component with the target user
     */
    public function mount(User $user): void
    {
        $this->user = $user;
        $this->isBlocked = Auth::check() && Auth::user()->hasBlocked($user);
    }

    /**
     * Toggle the visibility of the block confirmation modal
     */
    public function toggleBlockModal(): void
    {
        $this->showModal = ! $this->showModal;
        $this->blockReason = null;
    }

    /**
     * Process the block/unblock action after confirmation
     */
    public function confirmBlock(): void
    {
        if (! Auth::check()) {
            return;
        }

        $currentUser = Auth::user();

        // Check authorization - the policy methods are on BlockingPolicy
        if ($this->isBlocked) {
            if (! $currentUser->can('unblock', $this->user)) {
                return;
            }
        } else {
            if (! $currentUser->can('block', $this->user)) {
                return;
            }
        }

        $blockingService = app(UserBlockingService::class);

        if ($this->isBlocked) {
            $blockingService->unblockUser($currentUser, $this->user);
            $this->isBlocked = false;
            $this->dispatch('user-unblocked', userId: $this->user->id);
            flash()->success('You have successfully unblocked '.$this->user->name.'.');
        } else {
            $blockingService->blockUser($currentUser, $this->user, $this->blockReason);
            $this->isBlocked = true;
            $this->dispatch('user-blocked', userId: $this->user->id);
            Session::flash('success', 'You have successfully blocked '.$this->user->name.'.');

            // Redirect to homepage after blocking since the user profile will now be inaccessible
            $this->redirect(route('home'));

            return;
        }

        $this->showModal = false;
        $this->blockReason = null;
    }

    /**
     * Render the block button component
     */
    public function render(): View
    {
        return view('livewire.block-button');
    }
}
