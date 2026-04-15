<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a verification step fails expectedly (e.g., download error, safety check failure).
 */
final class VerificationFailedException extends RuntimeException {}
