<?php

declare(strict_types=1);

use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * Unblock a user from the blocked users list
     */
    public function unblockUser(int $userId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return;
        }

        $blockedUser = $currentUser->blocking()->where('blocked_id', $userId)->first()?->blocked;

        if ($blockedUser) {
            if (!$currentUser->can('unblock', $blockedUser)) {
                return;
            }

            $blockingService = resolve(UserBlockingService::class);
            $blockingService->unblockUser($currentUser, $blockedUser);

            $this->dispatch('user-unblocked', userId: $userId);
        }
    }

    /**
     * Get the blocked users with pagination
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\UserBlock>
     */
    public function getBlockedUsersProperty(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $user = Auth::user();
        if (! $user) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return $user->blocking()->with('blocked')->paginate(20);
    }
};
