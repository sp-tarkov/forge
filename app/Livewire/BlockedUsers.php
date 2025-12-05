<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BlockedUsers extends Component
{
    use WithPagination;

    /**
     * Unblock a user from the blocked users list
     */
    public function unblockUser(int $userId): void
    {
        $currentUser = Auth::user();
        $blockedUser = $currentUser->blocking()
            ->where('blocked_id', $userId)
            ->first()
            ?->blocked;

        if ($blockedUser) {
            if (! $currentUser->can('unblock', $blockedUser)) {
                return;
            }

            $blockingService = resolve(UserBlockingService::class);
            $blockingService->unblockUser($currentUser, $blockedUser);

            $this->dispatch('user-unblocked', userId: $userId);
        }
    }

    /**
     * Render the blocked users list with pagination
     */
    public function render(): View
    {
        $blockedUsers = Auth::user()
            ->blocking()
            ->with('blocked')
            ->paginate(20);

        return view('livewire.blocked-users', [
            'blockedUsers' => $blockedUsers,
        ]);
    }
}
