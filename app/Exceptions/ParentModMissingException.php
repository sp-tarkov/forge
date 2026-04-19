<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Addon;
use App\Models\ModList;
use Exception;

final class ParentModMissingException extends Exception
{
    public function __construct(
        public readonly ModList $modList,
        public readonly Addon $addon,
    ) {
        parent::__construct(sprintf(
            'Addon "%s" cannot be added to list "%s" without its parent mod.',
            $addon->name,
            $modList->title,
        ));
    }
}
