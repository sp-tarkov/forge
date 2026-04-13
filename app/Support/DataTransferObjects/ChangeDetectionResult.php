<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * Value object representing the result of a download link change detection check.
 */
final readonly class ChangeDetectionResult
{
    public function __construct(
        public bool $changed,
        public bool $unreachable,
        public ?int $contentLength = null,
        public ?string $etag = null,
        public ?string $lastModified = null,
        public ?int $httpStatus = null,
    ) {}
}
