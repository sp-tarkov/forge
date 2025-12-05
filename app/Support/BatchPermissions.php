<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A simple wrapper for batch-computed permissions.
 *
 * Provides a clean interface for checking pre-computed permissions in views.
 */
readonly class BatchPermissions
{
    /**
     * @param  array<int|string, array<string, bool>>  $permissions
     */
    public function __construct(
        private array $permissions = [],
    ) {}

    /**
     * Check if a permission is granted for a specific model.
     */
    public function can(int|string $modelKey, string $ability): bool
    {
        return $this->permissions[$modelKey][$ability] ?? false;
    }

    /**
     * Get all permissions for a specific model.
     *
     * @return array<string, bool>
     */
    public function for(int|string $modelKey): array
    {
        return $this->permissions[$modelKey] ?? [];
    }

    /**
     * Check if permissions exist for a specific model.
     */
    public function has(int|string $modelKey): bool
    {
        return isset($this->permissions[$modelKey]);
    }
}
