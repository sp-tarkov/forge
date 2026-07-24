<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * A dismissible chip describing one active mod index filter. The remove action holds a complete Livewire action
 * expression rendered onto the chip close button, for example "toggleVersionFilter('3.11.4')".
 */
final readonly class ActiveFilterChip
{
    public function __construct(
        public string $key,
        public string $label,
        public string $removeAction,
    ) {}
}
