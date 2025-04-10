<?php

declare(strict_types=1);

namespace App\Exceptions\Api\V0;

use Exception;
use Throwable;

class InvalidQuery extends Exception
{
    /**
     * Create a new invalid query exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
