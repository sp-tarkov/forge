<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use Exception;

final class ModListEntryDisabledException extends Exception
{
    public function __construct(
        public readonly ModList $modList,
        public readonly Mod|Addon $listable,
    ) {
        $name = $listable instanceof Mod ? $listable->name : $listable->name;

        parent::__construct(sprintf(
            '"%s" cannot be added to list "%s" because its author has opted out of mod lists.',
            $name,
            $modList->title,
        ));
    }
}
