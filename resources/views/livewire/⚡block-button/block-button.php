<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserBlockingService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public User $user;

    public bool $isBlocked = false;

    public bool $showModal = false;

    public ?string $blockReason = null;

    public string $size = 'sm';

    /**
     * Initialize the component with the target user
     */
    public function mount(User $user): void
    {
        $this->user = $user;
        $authUser = Auth::user();
        $this->isBlocked = $authUser !== null && $authUser->hasBlocked($user);
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
        if (! $currentUser) {
            return;
        }

        // Check authorization - the policy methods are on BlockingPolicy
        if ($this->isBlocked) {
            if (! $currentUser->can('unblock', $this->user)) {
                return;
            }
        } elseif (! $currentUser->can('block', $this->user)) {
            return;
        }

        $blockingService = resolve(UserBlockingService::class);

        if ($this->isBlocked) {
            $blockingService->unblockUser($currentUser, $this->user);
            $this->isBlocked = false;
            $this->dispatch('user-unblocked', userId: $this->user->id);
            Flux::toast(text: 'You have successfully unblocked '.$this->user->name.'.');
        } else {
            $blockingService->blockUser($currentUser, $this->user, $this->blockReason);
            $this->isBlocked = true;
            $this->dispatch('user-blocked', userId: $this->user->id);
            Flux::toast(text: 'You have successfully blocked '.$this->user->name.'.');

            // Redirect to homepage after blocking since the user profile will now be inaccessible
            $this->redirect(route('home'));

            return;
        }

        $this->showModal = false;
        $this->blockReason = null;
    }
};
