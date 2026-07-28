<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * A resolved mod version from a dependency query, paired with the queried identifiers that matched it.
 */
final readonly class QueriedModVersion
{
    /**
     * @param  list<string>  $identifiers
     */
    public function __construct(
        public int $modVersionId,
        public array $identifiers,
    ) {}
}
