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
     * The type of confirmation modal currently active.
     */
    public ?string $activeModal = null;

    /**
     * Load the action menu and compute permissions if needed.
     */
    public function loadMenu(): void
    {
        $this->menuOpen = true;
    }

    /**
     * Open a specific confirmation modal.
     */
    public function openModal(string $modalType): void
    {
        $this->activeModal = $modalType;
    }

    /**
     * Close the active modal.
     */
    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    /**
     * Clear cached permissions for the current model.
     */
    protected function clearPermissionCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }
}
