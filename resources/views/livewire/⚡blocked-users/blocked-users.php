<?php

declare(strict_types=1);

use App\Models\UserBlock;
use App\Services\UserBlockingService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
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
            if (! $currentUser->can('unblock', $blockedUser)) {
                return;
            }

            $blockingService = resolve(UserBlockingService::class);
            $blockingService->unblockUser($currentUser, $blockedUser);

            $this->dispatch('user-unblocked', userId: $userId);

            Flux::toast(heading: 'User Unblocked', text: $blockedUser->name.' has been unblocked.', variant: 'success');
        }
    }

    /**
     * Get the blocked users with pagination
     *
     * @return LengthAwarePaginator<int, UserBlock>
     */
    public function getBlockedUsersProperty(): LengthAwarePaginator
    {
        $user = Auth::user();
        if (! $user) {
            return new LengthAwarePaginator([], 0, 20);
        }

        return $user->blocking()->with('blocked')->paginate(20);
    }
};
