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
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\UserBlock>
     */
    public function getBlockedUsersProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Auth::user()->blocking()->with('blocked')->paginate(20);
    }
};
?>

<x-action-section>
    <x-slot name="title">
        {{ __('Blocked Users') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage users you have blocked. Blocked users cannot interact with you through comments, messages, or view your profile.') }}
    </x-slot>

    <x-slot name="content">
        @if ($this->blockedUsers->isEmpty())
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('You haven\'t blocked any users.') }}
            </div>
        @else
            <div class="space-y-2">
                @foreach ($this->blockedUsers as $block)
                    <div
                        class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center space-x-4">
                            <img
                                class="h-12 w-12 rounded-full"
                                src="{{ $block->blocked->profile_photo_url }}"
                                alt="{{ $block->blocked->name }}"
                            >
                            <div>
                                <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                                    {{ $block->blocked->name }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Blocked') }} {{ $block->created_at->diffForHumans() }}
                                </div>
                                @if ($block->reason)
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ __('Reason:') }} {{ $block->reason }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <flux:button
                                wire:click="unblockUser({{ $block->blocked->id }})"
                                variant="outline"
                                size="sm"
                                wire:loading.attr="disabled"
                                class="whitespace-nowrap"
                            >
                                <div class="flex items-center">
                                    <flux:icon.x-mark
                                        variant="micro"
                                        class="mr-1.5"
                                        wire:loading.remove
                                    />
                                    <flux:icon.arrow-path
                                        variant="micro"
                                        class="mr-1.5 animate-spin"
                                        wire:loading
                                    />
                                    {{ __('Unblock') }}
                                </div>
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->blockedUsers->hasPages())
                <div class="mt-4">
                    {{ $this->blockedUsers->links() }}
                </div>
            @endif
        @endif
    </x-slot>
</x-action-section>
