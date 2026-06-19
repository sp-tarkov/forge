<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Enums\NotificationColorRole;

final readonly class NotificationPresentation
{
    /**
     * @param  list<HeadlineSegment>  $headline
     * @param  list<NotificationDetail>  $details
     */
    public function __construct(
        public string $iconName,
        public NotificationColorRole $iconColorRole,
        public array $headline,
        public string $summary,
        public ?string $preview = null,
        public bool $previewQuoted = true,
        public ?string $url = null,
        public array $details = [],
    ) {}
}
