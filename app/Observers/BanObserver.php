<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ban;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class BanObserver
{
    /**
     * Handle the Ban "created" event.
     */
    public function created(Ban $ban): void
    {
        $this->forgetBanState($ban);
    }

    /**
     * Handle the Ban "updated" event.
     */
    public function updated(Ban $ban): void
    {
        $this->forgetBanState($ban);
    }

    /**
     * Handle the Ban "deleted" event.
     */
    public function deleted(Ban $ban): void
    {
        $this->forgetBanState($ban);
    }

    /**
     * Handle the Ban "restored" event.
     */
    public function restored(Ban $ban): void
    {
        $this->forgetBanState($ban);
    }

    /**
     * Forget the cached ban state for the banned user.
     */
    private function forgetBanState(Ban $ban): void
    {
        if ($ban->bannable_type === User::class && $ban->bannable_id !== null) {
            Cache::forget(User::banStateCacheKey($ban->bannable_id));
        }
    }
}
