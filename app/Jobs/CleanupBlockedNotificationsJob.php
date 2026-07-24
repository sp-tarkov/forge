<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Removes the blocker's database notifications that originate from the blocked user.
 */
final class CleanupBlockedNotificationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $blocker,
        public User $blocked
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->blocker->notifications()
            ->where(function (Builder $query): void {
                $query->where('data->commenter_id', $this->blocked->id)
                    ->orWhere('data->sender_id', $this->blocked->id);
            })
            ->delete();
    }
}
