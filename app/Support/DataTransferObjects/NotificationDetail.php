<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * A single detail line shown beneath a dashboard notification, optionally linked and annotated. Used by notifications
 * that need to surface a list of items (for example each affected mod version) rather than a single summary line.
 */
final readonly class NotificationDetail
{
    public function __construct(
        public string $label,
        public ?string $url = null,
        public ?string $note = null,
    ) {}
}
