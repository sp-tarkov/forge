<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

class UserBlockingService
{
    /**
     * Block a user and handle all related cleanup.
     */
    public function blockUser(User $blocker, User $blocked, ?string $reason = null): UserBlock
    {
        return DB::transaction(function () use ($blocker, $blocked, $reason) {
            $block = $blocker->block($blocked, $reason);
            $this->clearUserCaches($blocker, $blocked);

            return $block;
        });
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(User $blocker, User $blocked): bool
    {
        return DB::transaction(function () use ($blocker, $blocked) {
            $result = $blocker->unblock($blocked);
            if ($result) {
                $this->clearUserCaches($blocker, $blocked);
            }

            return $result;
        });
    }

    /**
     * Archive conversations between two users.
     */
    protected function archiveConversations(User $userOne, User $userTwo): void
    {
        // Find conversation between these two users
        $userId1 = min($userOne->id, $userTwo->id);
        $userId2 = max($userOne->id, $userTwo->id);

        $conversations = Conversation::query()
            ->where('user1_id', $userId1)
            ->where('user2_id', $userId2)
            ->get();

        foreach ($conversations as $conversation) {
            $existingArchive = ConversationArchive::query()->where('conversation_id', $conversation->id)
                ->where('user_id', $userOne->id)
                ->first();

            if (! $existingArchive) {
                ConversationArchive::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userOne->id,
                    'reason' => 'blocked',
                    'archived_at' => now(),
                ]);
            }

            $existingArchiveBlocked = ConversationArchive::query()->where('conversation_id', $conversation->id)
                ->where('user_id', $userTwo->id)
                ->first();

            if (! $existingArchiveBlocked) {
                ConversationArchive::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userTwo->id,
                    'reason' => 'blocked_by',
                    'archived_at' => now(),
                ]);
            }
        }
    }

    /**
     * Restore archived conversations between two users.
     */
    protected function restoreConversations(User $userOne, User $userTwo): void
    {
        $userId1 = min($userOne->id, $userTwo->id);
        $userId2 = max($userOne->id, $userTwo->id);

        ConversationArchive::query()
            ->whereIn('user_id', [$userOne->id, $userTwo->id])
            ->whereIn('reason', ['blocked', 'blocked_by'])
            ->whereHas('conversation', function ($query) use ($userId1, $userId2): void {
                $query->where('user1_id', $userId1)
                    ->where('user2_id', $userId2);
            })
            ->delete();
    }

    /**
     * Clear caches for both users.
     */
    protected function clearUserCaches(User $userOne, User $userTwo): void
    {
        cache()->forget(sprintf('user_%d_role_name', $userOne->id));
        cache()->forget(sprintf('user_%d_role_name', $userTwo->id));
    }
}
