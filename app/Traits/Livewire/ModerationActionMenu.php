<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use Illuminate\Support\Facades\Cache;

trait ModerationActionMenu
{
    /**
     * Whether the action menu is currently open.
     */
    public bool $menuOpen = false;

    /**
     * Load the action menu and compute permissions if needed.
     */
    public function loadMenu(): void
    {
        $this->menuOpen = true;
    }

    /**
     * Clear cached permissions for the current model.
     */
    protected function clearPermissionCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }
}
