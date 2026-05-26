<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ModList;
use Exception;

final class ModListCapacityExceededException extends Exception
{
    public function __construct(
        public readonly ModList $modList,
        public readonly int $attemptedCount,
        public readonly int $maxAllowed,
    ) {
        parent::__construct(sprintf(
            'Adding %d item(s) would exceed the list capacity of %d.',
            $attemptedCount,
            $maxAllowed,
        ));
    }
}
