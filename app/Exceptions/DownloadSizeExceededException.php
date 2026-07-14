<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a download's body grows past the maximum allowed file size, aborting the transfer mid-stream.
 */
final class DownloadSizeExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $bytesReceived,
        public readonly int $maxBytes,
    ) {
        parent::__construct(sprintf(
            'File size exceeds maximum (%d bytes) after receiving %d bytes',
            $maxBytes,
            $bytesReceived,
        ));
    }
}
